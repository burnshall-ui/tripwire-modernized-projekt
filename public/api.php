<?php

require_once('../config.php');

if (!TRIPWIRE_API) {
    header('HTTP/1.0 503 Service Unavailable');
    exit();
}

require_once('../db.inc.php');
require_once('../api/auth.php');
require_once('../services/SecurityHelper.php');

header('Content-Type: application/json');
$output = null;

try {
    // Validate and sanitize the 'q' parameter
    $q = SecurityHelper::validateString($_REQUEST['q'] ?? '', 'q', true, 500);
    $path = explode('/', $q);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}

if (isset($path[1])) {
    if ($path[1] == 'signatures') {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            include('../api/signatures/get.php');
        }
    } else if ($path[1] == 'wormholes') {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            include('../api/wormholes/get.php');
        }
    }
}

echo json_encode($output);
