<?php

namespace Tripwire\Services;

use SessionHandlerInterface;
use Exception;

class RedisSessionHandler implements SessionHandlerInterface {
    private RedisService $redis;
    private int $ttl;

    public function __construct(RedisService $redis, int $ttl = 86400) {
        $this->redis = $redis;
        $this->ttl = $ttl;
    }

    public function open(string $path, string $name): bool {
        // Redis connection is handled by RedisService
        return $this->redis->isConnected();
    }

    public function close(): bool {
        // Keep connection alive
        return true;
    }

    public function read(string $id): string {
        return $this->redis->sessionRead($id) ?: '';
    }

    public function write(string $id, string $data): bool {
        return $this->redis->sessionWrite($id, $data);
    }

    public function destroy(string $id): bool {
        return $this->redis->sessionDestroy($id);
    }

    public function gc(int $max_lifetime): bool {
        // Redis handles expiration automatically
        // We could implement manual cleanup here if needed
        return true;
    }

    /**
     * Initialize Redis-based session handling
     */
    public static function init(): bool {
        try {
            // Only initialize if Redis is available
            $redis = new RedisService();

            if ($redis->isConnected()) {
                $handler = new self($redis);

                // Register custom session handler
                session_set_save_handler($handler, true);

                // Configure session settings
                // Note: Do NOT set session.save_handler to 'redis' here
                // That would override our custom handler with PHP's native Redis session handler
                // Leave it as 'user' (default for custom handlers)
                ini_set('session.gc_maxlifetime', '86400'); // 24 hours
                ini_set('session.cookie_lifetime', '86400');
                ini_set('session.use_strict_mode', '1');

                return true;
            }
        } catch (Exception $e) {
            error_log("Redis session handler initialization failed: " . $e->getMessage());
        }

        // Fallback to default file-based sessions
        return false;
    }

    /**
     * Get session statistics
     */
    public function getStats(): array {
        if (!$this->redis->isConnected()) {
            return ['connected' => false];
        }

        // Get all session keys
        try {
            $keys = $this->redis->getRedis()->keys('tripwire:session:*');
            return [
                'connected' => true,
                'active_sessions' => count($keys),
                'session_keys' => array_slice($keys, 0, 10) // Show first 10
            ];
        } catch (Exception $e) {
            return [
                'connected' => true,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Clean up expired sessions manually (optional)
     */
    public function cleanupExpired(): int {
        // Redis handles expiration automatically, but we can implement
        // manual cleanup if needed for monitoring
        return 0;
    }
}
