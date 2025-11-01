<?php

require_once('../vendor/autoload.php'); // Composer autoload
require_once('../config.php');
require_once('../db.inc.php');
require_once('../services/SignatureService.php');
require_once('../services/WormholeService.php');

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class TripwireWebSocket implements MessageComponentInterface {
    protected SplObjectStorage $clients;
    protected PDO $db;
    protected SignatureService $signatureService;
    protected WormholeService $wormholeService;
    protected array $subscriptions = [];

    public function __construct(PDO $db) {
        $this->clients = new SplObjectStorage;
        $this->db = $db;
        $this->signatureService = new SignatureService($db);
        $this->wormholeService = new WormholeService($db);

        echo "Tripwire WebSocket Server started\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store connection
        $this->clients->attach($conn);
        $conn->userId = null;
        $conn->maskId = null;
        $conn->systemId = null;

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);

            if (!$data || !isset($data['action'])) {
                $from->send(json_encode(['error' => 'Invalid message format']));
                return;
            }

            switch ($data['action']) {
                case 'subscribe':
                    $this->handleSubscribe($from, $data);
                    break;

                case 'unsubscribe':
                    $this->handleUnsubscribe($from);
                    break;

                case 'ping':
                    $from->send(json_encode(['action' => 'pong', 'timestamp' => time()]));
                    break;

                default:
                    $from->send(json_encode(['error' => 'Unknown action']));
            }

        } catch (Exception $e) {
            echo "Message error: " . $e->getMessage() . "\n";
            $from->send(json_encode(['error' => 'Server error']));
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

        echo "Client {$conn->resourceId} subscribed to mask {$maskId}, system {$systemId}\n";
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

            $conn->send(json_encode([
                'action' => 'initial_data',
                'signatures' => array_map(fn($sig) => $sig->toArray(), $signatures),
                'wormholes' => array_map(fn($wh) => $wh->toArray(), $wormholes)
            ]));
        } catch (Exception $e) {
            echo "Error sending initial data: " . $e->getMessage() . "\n";
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

        foreach ($this->subscriptions[$subscriptionKey] as $conn) {
            try {
                $conn->send($message);
            } catch (Exception $e) {
                echo "Broadcast error: " . $e->getMessage() . "\n";
                $this->onClose($conn);
            }
        }
    }

    protected function validateMaskAccess(string $maskId): bool {
        // Simplified validation - should be replaced with proper auth
        // In production, this should validate against session/user permissions
        return !empty($maskId);
    }

    public function onClose(ConnectionInterface $conn) {
        // Remove from subscriptions
        $this->handleUnsubscribe($conn);

        // Remove connection
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Create and run the server
function startWebSocketServer(): void {
    global $mysql;

    if (!$mysql) {
        die("Database connection failed\n");
    }

    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new TripwireWebSocket($mysql)
            )
        ),
        8080, // WebSocket port
        '0.0.0.0'
    );

    echo "WebSocket server starting on port 8080...\n";
    $server->run();
}

// Run the server
startWebSocketServer();
