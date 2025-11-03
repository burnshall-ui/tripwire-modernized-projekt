<?php
//***********************************************************
//	File: 		masks.php
//	Author: 	Daimian
//	Created: 	8/19/2014
//	Modified: 	8/19/2014 - Daimian
//
//	Purpose:	Handles mask creation.
//
//	ToDo:
//***********************************************************
$startTime = microtime(true);

if (!session_id()) session_start();

// Check for login - else kick
if(!isset($_SESSION['userID'])) {
	http_response_code(403);
	exit();
}

require_once('../config.php');
require_once('../db.inc.php');
require_once('../lib.inc.php');
require_once('../services/SecurityHelper.php');

header('Content-Type: application/json');

try {
	// Validate mode parameter (enum)
	$mode = SecurityHelper::validateEnum(
		$_REQUEST['mode'] ?? null,
		['create', 'save', 'edit', 'delete', 'find', 'join', 'leave'],
		'mode',
		false // Not required - default action if missing
	);

	// Validate mask parameter (string/numeric)
	$mask = isset($_REQUEST['mask']) && !empty($_REQUEST['mask'])
		? SecurityHelper::validateString($_REQUEST['mask'], 'mask', false, 20)
		: null;

	// Validate type parameter (enum)
	$type = SecurityHelper::validateEnum(
		$_REQUEST['type'] ?? null,
		['corp', 'personal', 'corporate'],
		'type',
		false
	);

	// Validate name parameter (string)
	$name = isset($_REQUEST['name']) && !empty($_REQUEST['name'])
		? SecurityHelper::validateString($_REQUEST['name'], 'name', false, 100)
		: null;

	// Validate adds array (strings in format "id_type")
	$adds = isset($_REQUEST['adds']) && is_array($_REQUEST['adds'])
		? SecurityHelper::validateStringArray($_REQUEST['adds'], 'adds', false, 50)
		: array();

	// Validate deletes array (strings in format "id_type")
	$deletes = isset($_REQUEST['deletes']) && is_array($_REQUEST['deletes'])
		? SecurityHelper::validateStringArray($_REQUEST['deletes'], 'deletes', false, 50)
		: array();

	// Validate find parameter (enum - used in 'find' mode)
	$find = SecurityHelper::validateEnum(
		$_REQUEST['find'] ?? null,
		['personal', 'corporate'],
		'find',
		false
	);

	$output = null;

	// CSRF Protection for write operations (not needed for edit/find/list modes)
	$writeModes = ['create', 'save', 'delete', 'join', 'leave'];
	if (in_array($mode, $writeModes, true)) {
		SecurityHelper::requireCsrfToken();
	}
} catch (InvalidArgumentException $e) {
	http_response_code(400);
	$output['error'] = 'Validation error: ' . $e->getMessage();
	echo json_encode($output);
	exit();
}

if ($mode == 'create') {
	if (!$name) {
		$output['error'] = 'Mask must have a name';
	} else if (count($adds) == 0) {
		$output['error'] = 'Mask must have atleast one entity with access';
	} else if ($type == 'corp' && !$_SESSION['admin']) {
		$output['error'] = 'You are not a corporate admin';
	} else {
		$ownerID = $type == 'corp' ? $_SESSION['corporationID'] : $_SESSION['characterID'];
		$ownerType = $type == 'corp' ? 2 : 1373;

		if (in_array($ownerID.'_'.$ownerType, $adds)) {
			$output['error'] = 'Mask creator should not be in access list';
		} else {
			$query = 'SELECT MAX(maskID) AS mask FROM masks';
			$stmt = $mysql->prepare($query);
			$stmt->execute();
			$mask = $stmt->fetchColumn(0) +1;

			$query = 'INSERT INTO masks (maskID, name, ownerID, ownerType) VALUES (:maskID, :name, :ownerID, :ownerType)';
			$stmt = $mysql->prepare($query);
			$stmt->bindValue(':maskID', $mask);
			$stmt->bindValue(':name', $name);
			$stmt->bindValue(':ownerID', $ownerID);
			$stmt->bindValue(':ownerType', $ownerType);

			if ($stmt->execute()) {
				foreach ($adds as $add) {
					list($id, $type) = explode('_', $add);

					$query = 'INSERT INTO groups (maskID, eveID, eveType) VALUES (:mask, :id, :type)';
					$stmt = $mysql->prepare($query);
					$stmt->bindValue(':mask', $mask);
					$stmt->bindValue(':id', $id);
					$stmt->bindValue(':type', $type);
					$stmt->execute();
				}

				$output['result'] = true;
			} else {
				$output['error'] = 'Unable to create mask, possible duplicate name';
			}
		}
	}
} else if ($mode == 'save' && $mask && (checkOwner($mask) || checkAdmin($mask))) {
	foreach ($adds as $add) {
		list($id, $type) = explode('_', $add);

		$query = 'INSERT INTO groups (maskID, eveID, eveType) VALUES (:mask, :id, :type)';
		$stmt = $mysql->prepare($query);
		$stmt->bindValue(':mask', $mask);
		$stmt->bindValue(':id', $id);
		$stmt->bindValue(':type', $type);
		$stmt->execute();
	}

	foreach ($deletes as $delete) {
		list($id, $type) = explode('_', $delete);

		$query = 'DELETE FROM groups WHERE maskID = :mask AND eveID = :id AND eveType = :type';
		$stmt = $mysql->prepare($query);
		$stmt->bindValue(':mask', $mask);
		$stmt->bindValue(':id', $id);
		$stmt->bindValue(':type', $type);
		$stmt->execute();
	}

	$output['result'] = true;
} else if ($mode == 'edit' && $mask && (checkOwner($mask) || checkAdmin($mask))) {
	$query = 'SELECT eveID FROM masks INNER JOIN groups ON groups.maskID = masks.maskID WHERE masks.maskID = :mask';
	$stmt = $mysql->prepare($query);
	$stmt->bindValue(':mask', $mask);
	$stmt->execute();
	$output['results'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else if ($mode == 'delete' && $mask && (checkOwner($mask) || checkAdmin($mask))) {
	$query = 'DELETE masks, groups, comments, signatures FROM masks LEFT JOIN groups ON groups.maskID = masks.maskID LEFT JOIN comments ON comments.maskID = masks.maskID LEFT JOIN signatures ON signatures.maskID = masks.maskID WHERE masks.maskID = :mask';
	$stmt = $mysql->prepare($query);
	$stmt->bindValue(':mask', $mask);
	$output['result'] = $stmt->execute();
} else if ($mode == 'find') {
	$name = $name ? $name : '%';

	$query = "SELECT masks.maskID, name, ownerID, ownerType FROM masks INNER JOIN groups ON groups.maskID = masks.maskID WHERE ('personal' = :type AND joined = 0 AND eveID = :characterID AND eveType = 1373 AND name LIKE :name) OR ('corporate' = :type AND joined = 0 AND eveID = :corporationID AND eveType = 2 AND name LIKE :name)";
	$stmt = $mysql->prepare($query);
	$stmt->bindValue(':name', $name.'%');
	$stmt->bindValue(':characterID', $_SESSION['characterID']);
	$stmt->bindValue(':corporationID', $_SESSION['corporationID']);
	$stmt->bindValue(':type', $find); // Now using validated $find variable
	$stmt->execute();

	if ($stmt->rowCount()) {
		$ids = array();

		while ($row = $stmt->fetchObject()) {
			$ids[] = $row->ownerID;

			$output['results'][] = array(
				'mask' => $row->maskID,
				'label' => $row->name,
				'owner' => $row->ownerType == 1373 && $row->ownerID == $_SESSION['characterID']?true:false,
				'img' => $row->ownerType == 2?'https://image.eveonline.com/Corporation/'.$row->ownerID.'_64.png':'https://image.eveonline.com/Character/'.$row->ownerID.'_64.jpg'
			);
		}
		$output['eveIDs'] = $ids;
	} else {
		$output['error'] = "No masks found";
	}
} else if ($mode == 'join') {
	$query = 'UPDATE groups SET joined = 1 WHERE maskID = :mask AND ((eveID = :characterID AND eveType = 1373) OR (eveID = (SELECT corporationID FROM characters WHERE characterID = :characterID AND admin = 1) AND eveType = 2))';
	$stmt = $mysql->prepare($query);
	$stmt->bindValue(':characterID', $_SESSION['characterID']);
	$stmt->bindValue(':mask', $mask);
	$stmt->execute();

	if ($output['result'] = $stmt->rowCount()) {
		$query = 'SELECT eveType FROM groups WHERE maskID = :mask AND joined = 1';
		$stmt = $mysql->prepare($query);
		$stmt->bindValue(':mask', $mask);
		$stmt->execute();

		$output['type'] = $stmt->fetchColumn(0) == 2 ? 'corporate' : 'personal';
	}
} else if ($mode == 'leave') {
	$query = 'UPDATE groups SET joined = 0 WHERE maskID = :mask AND ((eveID = :characterID AND eveType = 1373) OR (eveID = (SELECT corporationID FROM characters WHERE characterID = :characterID AND admin = 1) AND eveType = 2))';
	$stmt = $mysql->prepare($query);
	$stmt->bindValue(':characterID', $_SESSION['characterID']);
	$stmt->bindValue(':mask', $mask);
	$stmt->execute();
	$output['result'] = $stmt->rowCount();
} else {
	$masks = array();

	// Public mask
	$output['masks'][] = array('mask' => '0.0', 'label' => 'Public', 'owner' => false, 'admin' => false, 'type' => 'default', 'img' => '//static.eve-apps.com/images/9_64_2.png');
	// Character mask
	$output['masks'][] = array('mask' => $_SESSION['characterID'].'.1', 'label' => 'Private', 'owner' => false, 'admin' => true, 'type' => 'default', 'img' => '//image.eveonline.com/Character/'.$_SESSION['characterID'].'_64.jpg');
	// Corporation mask
	$output['masks'][] = array('mask' => $_SESSION['corporationID'].'.2', 'label' => 'Corp', 'owner' => false, 'admin' => checkAdmin($_SESSION['corporationID'].'.2'), 'type' => 'default', 'img' => '//image.eveonline.com/Corporation/'.$_SESSION['corporationID'].'_64.png');

	// Custom masks
	$query = 'SELECT DISTINCT masks.maskID, name, ownerID, ownerType, eveID, eveType, admin, joined FROM masks LEFT JOIN groups ON groups.maskID = masks.maskID INNER JOIN characters ON characterID = :characterID WHERE (ownerID = :characterID AND ownerType = 1373) OR (ownerID = :corporationID AND ownerType = 2) OR (eveID = :characterID AND eveType = 1373 AND joined = 1) OR (eveID = :corporationID AND eveType = 2 AND joined = 1) GROUP BY masks.maskID';
	$stmt = $mysql->prepare($query);
	$stmt->bindValue(':characterID', $_SESSION['characterID']);
	$stmt->bindValue(':corporationID', $_SESSION['corporationID']);
	$stmt->execute();

	while ($row = $stmt->fetchObject()) {
		$output['masks'][] = array(
			'mask' => $row->maskID,
			'label' => $row->name,
			'optional' => ($row->admin && $row->eveID == $_SESSION['corporationID']) || $row->eveID == $_SESSION['characterID'] ? true : false,
			'owner' => $row->admin && $row->ownerID == $_SESSION['corporationID'] || $row->ownerID == $_SESSION['characterID'] ? true : false,
			'admin' => checkOwner($row->maskID) || checkAdmin($row->maskID) ? true : false,
			'type' => ($row->ownerID == $_SESSION['characterID'] && $row->ownerType == 1373) || ($row->eveID == $_SESSION['characterID'] && $row->eveType == 1373) ? 'personal' : 'corporate',
			'img' => $row->ownerType == 2?'https://image.eveonline.com/Corporation/'.$row->ownerID.'_64.png':'https://image.eveonline.com/Character/'.$row->ownerID.'_64.jpg'
		);
	}

	foreach ($output['masks'] AS $i => $mask) {
		if ($_SESSION['mask'] == $mask['mask']) {
			$output['active'] = $i;
		}
	}
}

$output['proccessTime'] = sprintf('%.4f', microtime(true) - $startTime);

echo json_encode($output);
