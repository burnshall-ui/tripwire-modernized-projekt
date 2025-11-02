<?php

$startTime = microtime(true);

// Load Composer autoloader (handles all PSR-4 autoloading)
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('ERROR: Composer autoloader not found. Run: composer install');
}
require_once(__DIR__ . '/vendor/autoload.php');

// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Load legacy config files
require_once('config.php');
require_once('settings.php');
require_once('db.inc.php');
require('lib.inc.php');

// Import namespaced classes
use Tripwire\Services\RedisSessionHandler;
use Tripwire\Services\ErrorHandler;
use function Tripwire\Services\createContainer;
use function Tripwire\Services\initErrorHandling;

// Initialize Redis-based session handling
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
