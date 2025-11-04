<?php

// Initialize Redis-based session handling
require_once('services/RedisService.php');
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
} else {
    // Redis session initialized - start session if not already started
    if (!session_id()) {
        session_start();
    }
}

$startTime = microtime(true);

// Load Composer autoloader if available (for Ratchet, Monolog, etc.)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once(__DIR__ . '/vendor/autoload.php');
}

require_once('config.php');
require_once('settings.php');
require_once('db.inc.php');
require('lib.inc.php');

// Load Models
require_once('models/Signature.php');
require_once('models/Wormhole.php');

// Load Services
require_once('services/RedisService.php');
require_once('services/DatabaseConnection.php');
require_once('services/ErrorHandler.php');
require_once('services/Container.php');
require_once('services/UserService.php');
require_once('services/SignatureService.php');
require_once('services/WormholeService.php');

// Load Controllers & Views
require_once('controllers/SystemController.php');
require_once('views/SystemView.php');

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
$view->setData('session', $_SESSION ?? []);

// Render the page
$view->renderHead();
$view->renderTopbar();
$view->renderUserPanel();
$view->renderFooter();
