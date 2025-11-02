<?php
/**
 * Health Check Endpoint
 *
 * Returns JSON status of critical services:
 * - Database connection
 * - Redis connection
 * - Session handler
 * - Disk space
 * - PHP version
 *
 * Usage: curl http://your-domain.com/health.php
 */

header('Content-Type: application/json');

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$startTime = microtime(true);

// Load Composer autoloader
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(503);
    die(json_encode([
        'status' => 'error',
        'message' => 'Composer autoloader not found',
        'timestamp' => date('c')
    ]));
}
require_once(__DIR__ . '/../vendor/autoload.php');

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// Load config files
@require_once(__DIR__ . '/../config.php');
@require_once(__DIR__ . '/../db.inc.php');

use Tripwire\Services\RedisService;

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'services' => [],
    'system' => []
];

// Check PHP Version
$health['system']['php_version'] = PHP_VERSION;
$health['system']['php_ok'] = version_compare(PHP_VERSION, '8.0.0', '>=');

// Check MySQL/MariaDB Connection
try {
    if (isset($mysql) && $mysql instanceof PDO) {
        $stmt = $mysql->query('SELECT VERSION() as version');
        $version = $stmt->fetch(PDO::FETCH_ASSOC);

        $health['services']['database'] = [
            'status' => 'up',
            'type' => 'mysql',
            'version' => $version['version'] ?? 'unknown',
            'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ];
    } else {
        $health['services']['database'] = [
            'status' => 'down',
            'error' => 'Database connection not initialized'
        ];
        $health['status'] = 'degraded';
    }
} catch (Exception $e) {
    $health['services']['database'] = [
        'status' => 'down',
        'error' => 'Connection failed'
    ];
    $health['status'] = 'unhealthy';
}

// Check Redis Connection
try {
    $redisStart = microtime(true);
    $redis = new RedisService();

    if ($redis->isConnected()) {
        $health['services']['redis'] = [
            'status' => 'up',
            'response_time_ms' => round((microtime(true) - $redisStart) * 1000, 2)
        ];
    } else {
        $health['services']['redis'] = [
            'status' => 'down',
            'note' => 'File-based sessions will be used as fallback'
        ];
        // Redis down is not critical (we have fallback)
        if ($health['status'] === 'healthy') {
            $health['status'] = 'degraded';
        }
    }
} catch (Exception $e) {
    $health['services']['redis'] = [
        'status' => 'down',
        'error' => $e->getMessage(),
        'note' => 'File-based sessions will be used as fallback'
    ];
    if ($health['status'] === 'healthy') {
        $health['status'] = 'degraded';
    }
}

// Check Session Handler
try {
    $health['services']['sessions'] = [
        'handler' => ini_get('session.save_handler'),
        'status' => 'ok'
    ];
} catch (Exception $e) {
    $health['services']['sessions'] = [
        'status' => 'error',
        'error' => $e->getMessage()
    ];
}

// Check Disk Space
$diskFree = @disk_free_space(__DIR__);
$diskTotal = @disk_total_space(__DIR__);

if ($diskFree !== false && $diskTotal !== false) {
    $diskUsedPercent = round((1 - ($diskFree / $diskTotal)) * 100, 2);
    $health['system']['disk'] = [
        'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
        'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2),
        'used_percent' => $diskUsedPercent,
        'status' => $diskUsedPercent > 90 ? 'warning' : 'ok'
    ];

    if ($diskUsedPercent > 95) {
        $health['status'] = 'degraded';
    }
}

// Check Logs Directory
$logsDir = __DIR__ . '/../logs';
if (!file_exists($logsDir)) {
    $health['system']['logs_dir'] = [
        'status' => 'missing',
        'note' => 'Will be created on first write'
    ];
} elseif (!is_writable($logsDir)) {
    $health['system']['logs_dir'] = [
        'status' => 'not_writable',
        'path' => $logsDir
    ];
    $health['status'] = 'degraded';
} else {
    $health['system']['logs_dir'] = [
        'status' => 'ok',
        'writable' => true
    ];
}

// Overall response time
$health['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

// Set appropriate HTTP status code
switch ($health['status']) {
    case 'healthy':
        http_response_code(200);
        break;
    case 'degraded':
        http_response_code(200); // Still operational
        break;
    case 'unhealthy':
        http_response_code(503); // Service Unavailable
        break;
}

// Return JSON
echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
