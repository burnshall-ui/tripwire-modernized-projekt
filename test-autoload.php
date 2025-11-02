<?php
/**
 * Quick Autoload Test
 *
 * Tests if PSR-4 autoloading works correctly
 * Run: php test-autoload.php
 */

echo "🧪 Testing Tripwire 2.1 Autoloading...\n\n";

// Load Composer autoloader
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("❌ ERROR: Composer autoloader not found.\n   Run: composer install\n");
}
require_once(__DIR__ . '/vendor/autoload.php');

$tests = [
    'Services' => [
        'Tripwire\\Services\\Container',
        'Tripwire\\Services\\RedisService',
        'Tripwire\\Services\\UserService',
        'Tripwire\\Services\\SignatureService',
        'Tripwire\\Services\\WormholeService',
        'Tripwire\\Services\\ErrorHandler',
        'Tripwire\\Services\\RedisSessionHandler',
        'Tripwire\\Services\\Logger',
    ],
    'Controllers' => [
        'Tripwire\\Controllers\\SystemController',
    ],
    'Models' => [
        'Tripwire\\Models\\Signature',
        'Tripwire\\Models\\Wormhole',
    ],
    'Views' => [
        'Tripwire\\Views\\SystemView',
    ]
];

$passed = 0;
$failed = 0;

foreach ($tests as $category => $classes) {
    echo "📦 Testing {$category}:\n";

    foreach ($classes as $class) {
        if (class_exists($class)) {
            echo "  ✅ {$class}\n";
            $passed++;
        } else {
            echo "  ❌ {$class} - NOT FOUND!\n";
            $failed++;
        }
    }

    echo "\n";
}

// Test Logger
echo "🔍 Testing Logger:\n";
try {
    use Tripwire\Services\Logger;

    // Create logs directory if it doesn't exist
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Try to write a test log
    Logger::info("Autoload test successful!");
    echo "  ✅ Logger::info() works\n";
    $passed++;
} catch (Exception $e) {
    echo "  ❌ Logger failed: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n";

// Test .env loading
echo "🔧 Testing .env support:\n";
if (file_exists(__DIR__ . '/.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        echo "  ✅ .env file loaded\n";
        echo "  ℹ️  APP_ENV: " . (getenv('APP_ENV') ?: 'not set') . "\n";
        $passed++;
    } catch (Exception $e) {
        echo "  ❌ .env loading failed: " . $e->getMessage() . "\n";
        $failed++;
    }
} else {
    echo "  ⚠️  .env file not found (optional)\n";
    echo "  ℹ️  Copy .env.example to .env for environment config\n";
}

echo "\n";

// Summary
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Test Results:\n";
echo "   ✅ Passed: {$passed}\n";
echo "   ❌ Failed: {$failed}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

if ($failed === 0) {
    echo "🎉 All tests passed! Autoloading works correctly.\n";
    echo "\n";
    echo "Next steps:\n";
    echo "  1. Create .env file: cp .env.example .env\n";
    echo "  2. Configure .env with your database credentials\n";
    echo "  3. Test health check: curl http://localhost/health.php\n";
    echo "  4. Check logs: tail -f logs/tripwire.log\n";
    exit(0);
} else {
    echo "❌ Some tests failed. Please fix before continuing.\n";
    echo "\n";
    echo "Common fixes:\n";
    echo "  - Run: composer dump-autoload --optimize\n";
    echo "  - Check file permissions\n";
    echo "  - Verify all files exist in services/, controllers/, models/, views/\n";
    exit(1);
}
