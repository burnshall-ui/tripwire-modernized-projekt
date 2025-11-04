<?php

// SSO redirecting
$state = isset($_REQUEST['state'])?$_REQUEST['state']:null;
if ($state == 'evessologin' || $state == 'evessoesi') {
	require('login.php');
	exit();
} else if ($state == 'evessoregisteruser' || $state == 'evessoregisteradmin') {
	require('register.php');
	exit();
}

// Check if we're loading the map - tripwire.php handles its own sessions
if (isset($_GET['system'])) {
	// Close any existing session before tripwire.php starts its Redis session
	if (session_id()) {
		session_write_close();
	}
	// tripwire.php will start its own session
	require('../tripwire.php');
	exit;
}

// For landing page: Start secure session
if (!session_id()) {
	session_start([
		'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
		'cookie_httponly' => true,
		'cookie_samesite' => 'Strict',
		'use_strict_mode' => true,
		'use_only_cookies' => true
	]);
}

if (!isset($_SESSION['username']) && isset($_COOKIE['tripwire']))
	include('login.php');

// Show landing page
require('../landing.php');

?>
