<?php

// Rate limiting for API authentication
$clientIP = $_SERVER['REMOTE_ADDR'];
$rateLimitKey = "api_auth_{$clientIP}";
$maxAttempts = 10; // Max attempts per hour
$timeWindow = 3600; // 1 hour in seconds

// Simple file-based rate limiting (consider Redis for production)
$rateLimitFile = sys_get_temp_dir() . "/tripwire_rate_limit_{$rateLimitKey}";
$currentTime = time();

if (file_exists($rateLimitFile)) {
    $rateData = json_decode(file_get_contents($rateLimitFile), true);
    if ($rateData && $currentTime - $rateData['window_start'] < $timeWindow) {
        if ($rateData['attempts'] >= $maxAttempts) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Retry-After: ' . ($timeWindow - ($currentTime - $rateData['window_start'])));
            echo 'Rate limit exceeded. Please try again later.';
            exit;
        }
        $rateData['attempts']++;
    } else {
        $rateData = ['attempts' => 1, 'window_start' => $currentTime];
    }
} else {
    $rateData = ['attempts' => 1, 'window_start' => $currentTime];
}

file_put_contents($rateLimitFile, json_encode($rateData));

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Tripwire"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication is required';
    exit;
}

$username = $_SERVER['PHP_AUTH_USER'] ?? '';
$password = $_SERVER['PHP_AUTH_PW'] ?? '';
$characterID = null;
$corporationID = null;
$maskID = null;

$query = 'SELECT * FROM accounts WHERE username = :username';
$stmt = $mysql->prepare($query);
$stmt->bindValue(':username', $username);
$stmt->execute();
if ($account = $stmt->fetchObject()) {
    require('../password_hash.php');
    $hasher = new PasswordHash(8, FALSE);
    if ($account->ban == 1 || $hasher->CheckPassword($password, $account->password) == false) {
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authentication failed';
        exit;
    }
} else {
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication failed';
    exit;
}

// Verify has access to requested mask data
if (isset($_REQUEST['maskID']) && !empty($_REQUEST['maskID'])) {
    $query = 'SELECT * FROM characters WHERE userID = :userID';
    $stmt = $mysql->prepare($query);
    $stmt->bindValue(':userID', $account->id);
    $stmt->execute();
    if ($characters = $stmt->fetchAll(PDO::FETCH_CLASS)) {
        foreach ($characters AS $character) {
            $checkMask = explode('.', $_REQUEST['maskID']);
            if (count($checkMask) != 2) {
              header('HTTP/1.0 400 Bad Request');
              echo 'MaskID must be a decimal';
              exit;
            }

            if ($checkMask[1] == 0 && $checkMask[0] != 0) {
                $query = 'SELECT masks.maskID as maskID FROM masks INNER JOIN groups ON masks.maskID = groups.maskID WHERE (ownerID = :characterID AND ownerType = 1373) OR (ownerID = :corporationID AND ownerType = 2) OR (eveID = :characterID AND eveType = 1373) OR (eveID = :corporationID AND eveType = 2)';
                $stmt = $mysql->prepare($query);
                $stmt->bindValue(':characterID', $character->characterID);
                $stmt->bindValue(':corporationID', $character->corporationID);
                $stmt->execute();
                $mask = $stmt->fetchObject();
                if ($mask && $mask->maskID == $_REQUEST['maskID']) {
                    $maskID = $mask->maskID;
                    break;
                }
            } else if ($checkMask[1] == 1 && $checkMask[0] == $character->characterID) {
                $maskID = $character->characterID . '.1';
                break;
            } else if ($checkMask[1] == 2 && $checkMask[0] == $character->corporationID) {
                $maskID = $character->corporationID . '.2';
                break;
            }
        }
    }
}
