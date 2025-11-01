<?php

// Initialize Redis-based session handling
require_once('services/RedisSessionHandler.php');
$redisSessionInitialized = RedisSessionHandler::init();

// Fallback to secure file-based sessions if Redis is not available
if (!$redisSessionInitialized) {
    if (!session_id()) {
        session_start([
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'use_only_cookies' => true
        ]);
    }
}

$startTime = microtime(true);

require_once('config.php');
require_once('settings.php');
require_once('db.inc.php');
require('lib.inc.php');

// Load new modular architecture
require_once('services/Container.php');
require_once('services/DatabaseConnection.php');
require_once('controllers/SystemController.php');
require_once('services/UserService.php');
require_once('services/SignatureService.php');
require_once('services/WormholeService.php');
require_once('services/RedisService.php');
require_once('services/RedisSessionHandler.php');
require_once('views/SystemView.php');
require_once('services/ErrorHandler.php');

// Initialize error handling
$errorHandler = initErrorHandling();

// Initialize container and services
$container = createContainer();
$systemController = $container->get('systemController');
$userService = $container->get('userService');
$signatureService = $container->get('signatureService');
$wormholeService = $container->get('wormholeService');
$view = $container->get('systemView');

// Get requested system or default
$requestedSystem = $_REQUEST['system'] ?? 'Jita';
$systemData = $systemController->resolveSystem($requestedSystem);

// Track user activity
if (isset($_SESSION['userID'])) {
    $userService->trackUserActivity($_SESSION['userID']);
}

// Prepare view data
$view->setData('system', $systemData['system']);
$view->setData('systemID', $systemData['systemID']);
$view->setData('region', $systemData['region']);
$view->setData('regionID', $systemData['regionID']);
$view->setData('user', $userService->getUserData());
$view->setData('session', $_SESSION);

// Render the page
$view->renderHead();
$view->renderTopbar();
$view->renderUserPanel();
$view->renderFooter();
