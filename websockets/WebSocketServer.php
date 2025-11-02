<?php

// Load Composer autoloader (required for Ratchet)
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    die("Composer dependencies not installed. Run: composer install\n");
}
require_once(__DIR__ . '/../vendor/autoload.php');

// Load configuration
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db.inc.php');

// Load Models
require_once(__DIR__ . '/../models/Signature.php');
require_once(__DIR__ . '/../models/Wormhole.php');

// Load Services
require_once(__DIR__ . '/../services/RedisService.php');
require_once(__DIR__ . '/../services/ErrorHandler.php');
require_once(__DIR__ . '/../services/Container.php');
require_once(__DIR__ . '/../services/UserService.php');
require_once(__DIR__ . '/../services/SignatureService.php');
require_once(__DIR__ . '/../services/WormholeService.php');

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class TripwireWebSocket implements MessageComponentInterface {
    protected SplObjectStorage $clients;
    protected Container $container;
    protected SignatureService $signatureService;
    protected WormholeService $wormholeService;
    protected array $subscriptions = [];

    public function __construct(Container $container) {
        $this->clients = new SplObjectStorage;
        $this->container = $container;
        $this->signatureService = $container->get('signatureService');
        $this->wormholeService = $container->get('wormholeService');

        echo "[" . date('Y-m-d H:i:s') . "] Tripwire WebSocket Server started\n";
        echo "[" . date('Y-m-d H:i:s') . "] Listening on port 8080\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store connection
        $this->clients->attach($conn);
        $conn->userId = null;
        $conn->maskId = null;
        $conn->systemId = null;
        $conn->authenticated = false;

        $this->logInfo($conn, "New connection established");
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);

            if (!$data || !isset($data['action'])) {
                $this->logError($from, "Invalid message format");
                $from->send(json_encode(['error' => 'Invalid message format']));
                return;
            }

            $action = $data['action'];
            $this->logDebug($from, "Received action: {$action}");

            switch ($action) {
                case 'subscribe':
                    $this->handleSubscribe($from, $data);
                    break;

                case 'unsubscribe':
                    $this->handleUnsubscribe($from);
                    break;

                case 'ping':
                    $from->send(json_encode([
                        'action' => 'pong', 
                        'timestamp' => time(),
                        'server_time' => date('Y-m-d H:i:s')
                    ]));
                    break;

                case 'authenticate':
                    $this->handleAuthentication($from, $data);
                    break;

                default:
                    $this->logError($from, "Unknown action: {$action}");
                    $from->send(json_encode(['error' => 'Unknown action']));
            }

        } catch (Exception $e) {
            $this->logError($from, "Message error: " . $e->getMessage());
            $from->send(json_encode([
                'error' => 'Server error',
                'message' => defined('DEBUG') && DEBUG ? $e->getMessage() : 'Internal error'
            ]));
        }
    }

    protected function handleSubscribe(ConnectionInterface $conn, array $data): void {
        if (!isset($data['maskId']) || !isset($data['systemId'])) {
            $conn->send(json_encode(['error' => 'maskId and systemId required']));
            return;
        }

        $maskId = $data['maskId'];
        $systemId = $data['systemId'];

        // Validate permissions (simplified - should use proper auth)
        if (!$this->validateMaskAccess($maskId)) {
            $conn->send(json_encode(['error' => 'Access denied']));
            return;
        }

        // Store subscription info
        $conn->maskId = $maskId;
        $conn->systemId = $systemId;

        // Add to subscriptions array for broadcasting
        $subscriptionKey = $maskId . '_' . $systemId;
        if (!isset($this->subscriptions[$subscriptionKey])) {
            $this->subscriptions[$subscriptionKey] = [];
        }
        $this->subscriptions[$subscriptionKey][] = $conn;

        // Send initial data
        $this->sendInitialData($conn, $maskId, $systemId);

        $conn->send(json_encode([
            'action' => 'subscribed',
            'maskId' => $maskId,
            'systemId' => $systemId
        ]));

        $this->logInfo($conn, "Subscribed to mask: {$maskId}, system: {$systemId}");
    }

    protected function handleUnsubscribe(ConnectionInterface $conn): void {
        if ($conn->maskId && $conn->systemId) {
            $subscriptionKey = $conn->maskId . '_' . $conn->systemId;
            if (isset($this->subscriptions[$subscriptionKey])) {
                $key = array_search($conn, $this->subscriptions[$subscriptionKey]);
                if ($key !== false) {
                    unset($this->subscriptions[$subscriptionKey][$key]);
                }
            }
        }

        $conn->maskId = null;
        $conn->systemId = null;
        $conn->send(json_encode(['action' => 'unsubscribed']));
    }

    protected function sendInitialData(ConnectionInterface $conn, string $maskId, int $systemId): void {
        try {
            // Get current signatures and wormholes
            $signatures = $this->signatureService->getBySystem($systemId, $maskId);
            $wormholes = $this->wormholeService->getBySystem($systemId, $maskId);

            $sigCount = count($signatures);
            $whCount = count($wormholes);

            $conn->send(json_encode([
                'action' => 'initial_data',
                'signatures' => array_map(fn($sig) => $sig->toArray(), $signatures),
                'wormholes' => array_map(fn($wh) => $wh->toArray(), $wormholes)
            ]));

            $this->logDebug($conn, "Sent initial data: {$sigCount} signatures, {$whCount} wormholes");
        } catch (Exception $e) {
            $this->logError($conn, "Error sending initial data: " . $e->getMessage());
        }
    }

    public function broadcastUpdate(string $maskId, int $systemId, string $type, array $data): void {
        $subscriptionKey = $maskId . '_' . $systemId;

        if (!isset($this->subscriptions[$subscriptionKey])) {
            return;
        }

        $message = json_encode([
            'action' => 'update',
            'type' => $type,
            'data' => $data,
            'timestamp' => time()
        ]);

        $broadcastCount = 0;
        foreach ($this->subscriptions[$subscriptionKey] as $conn) {
            try {
                $conn->send($message);
                $broadcastCount++;
            } catch (Exception $e) {
                $this->logError($conn, "Broadcast error: " . $e->getMessage());
                $this->onClose($conn);
            }
        }

        if ($broadcastCount > 0) {
            $this->logDebug(null, "Broadcasted {$type} update to {$broadcastCount} client(s) on {$subscriptionKey}");
        }
    }

    protected function validateMaskAccess(string $maskId, ?int $userId = null): bool {
        // Basic validation
        if (empty($maskId)) {
            return false;
        }

        // If userId is provided, use UserService to validate
        if ($userId !== null) {
            try {
                $userService = $this->container->get('userService');
                // Prüfe ob User Zugriff auf Mask hat
                // TODO: Implementiere richtige Permission-Checks
                return true;
            } catch (Exception $e) {
                $this->logError(null, "Mask validation error: " . $e->getMessage());
                return false;
            }
        }

        // Fallback: Allow if mask is not empty (simplified)
        return true;
    }

    protected function handleAuthentication(ConnectionInterface $conn, array $data): void {
        if (!isset($data['userId']) || !isset($data['token'])) {
            $conn->send(json_encode(['error' => 'userId and token required']));
            return;
        }

        // TODO: Implement proper token validation
        // For now, just store the userId
        $conn->userId = $data['userId'];
        $conn->authenticated = true;

        $this->logInfo($conn, "User authenticated: {$data['userId']}");

        $conn->send(json_encode([
            'action' => 'authenticated',
            'userId' => $data['userId']
        ]));
    }

    protected function logDebug(?ConnectionInterface $conn, string $message): void {
        $connId = $conn ? $conn->resourceId : 'N/A';
        echo "[DEBUG] [{$connId}] {$message}\n";
    }

    protected function logInfo(?ConnectionInterface $conn, string $message): void {
        $connId = $conn ? $conn->resourceId : 'N/A';
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [INFO] [{$connId}] {$message}\n";
    }

    protected function logError(?ConnectionInterface $conn, string $message): void {
        $connId = $conn ? $conn->resourceId : 'N/A';
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [ERROR] [{$connId}] {$message}\n";
        error_log("[WebSocket] [ERROR] [{$connId}] {$message}");
    }

    public function onClose(ConnectionInterface $conn) {
        // Remove from subscriptions
        $this->handleUnsubscribe($conn);

        // Remove connection
        $this->clients->detach($conn);

        $this->logInfo($conn, "Connection closed");
    }

    public function onError(ConnectionInterface $conn, Exception $e) {
        $this->logError($conn, "Connection error: {$e->getMessage()}");
        $conn->close();
    }
}

// Create and run the server
function startWebSocketServer(): void {
    global $mysql;

    if (!$mysql) {
        die("[ERROR] Database connection failed\n");
    }

    // Initialize error handling
    $errorHandler = initErrorHandling();

    // Create container with services
    $container = createContainer();

    echo "[" . date('Y-m-d H:i:s') . "] Initializing WebSocket server...\n";
    echo "[" . date('Y-m-d H:i:s') . "] Services loaded: " . implode(', ', $container->getServiceIds()) . "\n";

    // Check Redis connection
    try {
        $redis = $container->get('redis');
        if ($redis->isConnected()) {
            echo "[" . date('Y-m-d H:i:s') . "] Redis connection: OK\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] Redis connection: FAILED (running without cache)\n";
        }
    } catch (Exception $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Redis error: " . $e->getMessage() . "\n";
    }

    // Create WebSocket server
    $tripwireWs = new TripwireWebSocket($container);

    $server = IoServer::factory(
        new HttpServer(
            new WsServer($tripwireWs)
        ),
        8080, // WebSocket port
        '0.0.0.0'
    );

    echo "[" . date('Y-m-d H:i:s') . "] WebSocket server ready. Waiting for connections...\n";
    echo "========================================\n";

    // Run the server (blocking)
    $server->run();
}

// Signal handling for graceful shutdown
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        echo "\n[" . date('Y-m-d H:i:s') . "] Received SIGTERM, shutting down...\n";
        exit(0);
    });

    pcntl_signal(SIGINT, function() {
        echo "\n[" . date('Y-m-d H:i:s') . "] Received SIGINT (Ctrl+C), shutting down...\n";
        exit(0);
    });
}

// Run the server
echo "========================================\n";
echo "  Tripwire WebSocket Server v2.0\n";
echo "========================================\n";

startWebSocketServer();
