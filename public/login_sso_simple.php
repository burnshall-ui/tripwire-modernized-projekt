<?php
/**
 * Simplified SSO Login with Full Error Display
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!session_id()) session_start();

require_once('../config.php');
require_once('../db.inc.php');
require_once('../esi.class.php');

$code = isset($_REQUEST['code']) ? $_REQUEST['code'] : null;
$state = isset($_REQUEST['state']) ? $_REQUEST['state'] : null;
$ip = $_SERVER['REMOTE_ADDR'];

echo "<!DOCTYPE html><html><head><title>SSO Login Debug</title></head><body>";
echo "<h1>Tripwire SSO Login</h1>";

if (!$code || $state !== 'evessologin') {
    echo "<p style='color: red;'>❌ Invalid SSO callback. Missing code or wrong state.</p>";
    echo "<p>Code: " . htmlspecialchars($code) . "</p>";
    echo "<p>State: " . htmlspecialchars($state) . "</p>";
    echo "<p><a href='./'>Go back to homepage</a></p>";
    echo "</body></html>";
    exit;
}

echo "<h2>Step 1: Authenticating with EVE SSO...</h2>";

$esi = new esi();

if (!$esi->authenticate($code)) {
    echo "<p style='color: red;'>❌ EVE SSO Authentication failed!</p>";
    echo "<p>Error: " . htmlspecialchars($esi->lastError) . "</p>";
    echo "<p>HTTP Code: " . $esi->httpCode . "</p>";
    echo "<p><a href='./'>Try again</a></p>";
    echo "</body></html>";
    exit;
}

echo "<p style='color: green;'>✅ Authentication successful!</p>";
echo "<p>Character ID: " . $esi->characterID . "</p>";
echo "<p>Character Name: " . $esi->characterName . "</p>";

echo "<h2>Step 2: Looking up account in database...</h2>";

try {
    $query = 'SELECT id, username, password, accounts.ban, characterID, characterName, corporationID, corporationName, admin, super, options
              FROM accounts
              LEFT JOIN preferences ON id = preferences.userID
              LEFT JOIN characters ON id = characters.userID
              WHERE characterID = :characterID';
    $stmt = $mysql->prepare($query);
    $stmt->bindValue(':characterID', $esi->characterID);
    $stmt->execute();

    $account = $stmt->fetchObject();

    if (!$account) {
        echo "<p style='color: red;'>❌ No account found for this character!</p>";
        echo "<p>Character ID: " . $esi->characterID . "</p>";
        echo "<p>You need to register first: <a href='./#register'>Register here</a></p>";
        echo "</body></html>";
        exit;
    }

    echo "<p style='color: green;'>✅ Account found!</p>";
    echo "<p>Username: " . htmlspecialchars($account->username) . "</p>";
    echo "<p>Corporation: " . htmlspecialchars($account->corporationName) . "</p>";
    echo "<p>Admin: " . ($account->admin ? 'Yes' : 'No') . "</p>";

    if ($account->ban == 1) {
        echo "<p style='color: red;'>❌ This account is banned!</p>";
        echo "</body></html>";
        exit;
    }

    echo "<h2>Step 3: Creating session...</h2>";

    $options = json_decode($account->options);

    $_SESSION['userID'] = $account->id;
    $_SESSION['username'] = $account->username;
    $_SESSION['ip'] = $ip;
    $_SESSION['mask'] = @$options->masks->active ? $options->masks->active : $account->corporationID . '.2';
    $_SESSION['characterID'] = $account->characterID;
    $_SESSION['characterName'] = $account->characterName;
    $_SESSION['corporationID'] = $account->corporationID;
    $_SESSION['corporationName'] = $account->corporationName;
    $_SESSION['admin'] = $account->admin;
    $_SESSION['super'] = $account->super;
    $_SESSION['options'] = $options;

    echo "<p style='color: green;'>✅ Session created!</p>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";

    echo "<h2>Step 4: Logging login...</h2>";

    $query = 'INSERT INTO _history_login (ip, username, method, result) VALUES (:ip, :username, :method, :result)';
    $stmt = $mysql->prepare($query);
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':username', $account->username);
    $stmt->bindValue(':method', 'sso');
    $stmt->bindValue(':result', 'success');
    $stmt->execute();

    echo "<p style='color: green;'>✅ Login logged!</p>";

    echo "<h2>Step 5: Updating login count...</h2>";

    $query = 'UPDATE accounts SET logins = logins + 1, lastLogin = NOW() WHERE id = :userID';
    $stmt = $mysql->prepare($query);
    $stmt->bindValue(':userID', $account->id);
    $stmt->execute();

    echo "<p style='color: green;'>✅ Login count updated!</p>";

    echo "<hr>";
    echo "<h2 style='color: green;'>🎉 LOGIN SUCCESSFUL!</h2>";
    echo "<p><strong>Click here to access Tripwire:</strong> <a href='./'>Go to Map</a></p>";
    echo "<p>Or auto-redirect in 5 seconds...</p>";
    echo "<script>setTimeout(function(){ window.location.href = './'; }, 5000);</script>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}

echo "</body></html>";
?>
