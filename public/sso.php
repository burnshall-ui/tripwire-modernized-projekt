<?php
/**
 * Unified EVE SSO Callback Handler
 * Routes SSO callbacks to appropriate handlers based on state parameter
 */

$code = isset($_REQUEST['code']) ? $_REQUEST['code'] : null;
$state = isset($_REQUEST['state']) ? $_REQUEST['state'] : null;

if (!$code || !$state) {
    header('Location: ./?error=sso-invalid');
    exit();
}

// Route based on state parameter
switch ($state) {
    case 'evessologin':
        // Login flow - use simplified SSO login
        header('Location: login_sso_simple.php?code=' . urlencode($code) . '&state=' . urlencode($state));
        break;

    case 'evessoesi':
        // ESI flow - forward to login.php
        header('Location: login.php?code=' . urlencode($code) . '&state=' . urlencode($state));
        break;

    case 'evessoregisteruser':
    case 'evessoregisteradmin':
        // Registration flow - forward to register.php
        header('Location: register.php?code=' . urlencode($code) . '&state=' . urlencode($state));
        break;

    default:
        // Unknown state
        header('Location: ./?error=sso-unknown');
        break;
}

exit();
