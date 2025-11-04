<?php

class RedisService {
    private ?Redis $redis = null;
    private bool $connected = false;
    private array $config;

    public function __construct(array $config = []) {
        $this->config = array_merge([
            'host' => 'tripwire-redis',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'timeout' => 1.0,
            'persistent' => true,
            'prefix' => 'tripwire:'
        ], $config);

        $this->connect();
    }

    private function connect(): bool {
        if ($this->connected) {
            return true;
        }

        try {
            $this->redis = new Redis();

            if ($this->config['persistent']) {
                $this->connected = $this->redis->pconnect(
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['timeout']
                );
            } else {
                $this->connected = $this->redis->connect(
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['timeout']
                );
            }

            if ($this->connected) {
                if ($this->config['password']) {
                    $this->redis->auth($this->config['password']);
                }

                $this->redis->select($this->config['database']);
                $this->redis->setOption(Redis::OPT_PREFIX, $this->config['prefix']);
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);

                return true;
            }
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
        }

        return false;
    }

    public function isConnected(): bool {
        if (!$this->connected || !$this->redis) {
            return false;
        }

        try {
            $ping = $this->redis->ping();
            // Different Redis extension versions return different values:
            // - Older versions: '+PONG' (string)
            // - Newer versions: true (boolean)
            return $ping === true || $ping === '+PONG' || $ping === 'PONG';
        } catch (Exception $e) {
            error_log("Redis ping failed: " . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }

    // ================================
    // Basic Cache Operations
    // ================================

    public function get(string $key) {
        if (!$this->ensureConnection()) {
            return null;
        }

        try {
            $value = $this->redis->get($key);
            return $value;
        } catch (Exception $e) {
            error_log("Redis GET error: " . $e->getMessage());
            return null;
        }
    }

    public function set(string $key, $value, int $ttl = 0): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, $value);
            } else {
                return $this->redis->set($key, $value);
            }
        } catch (Exception $e) {
            error_log("Redis SET error: " . $e->getMessage());
            return false;
        }
    }

    public function delete(string $key): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            return $this->redis->del($key) > 0;
        } catch (Exception $e) {
            error_log("Redis DELETE error: " . $e->getMessage());
            return false;
        }
    }

    public function exists(string $key): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            return $this->redis->exists($key);
        } catch (Exception $e) {
            error_log("Redis EXISTS error: " . $e->getMessage());
            return false;
        }
    }

    public function expire(string $key, int $ttl): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            return $this->redis->expire($key, $ttl);
        } catch (Exception $e) {
            error_log("Redis EXPIRE error: " . $e->getMessage());
            return false;
        }
    }

    // ================================
    // Advanced Cache Operations
    // ================================

    public function getMultiple(array $keys): array {
        if (!$this->ensureConnection()) {
            return [];
        }

        try {
            $values = $this->redis->mget($keys);
            return array_combine($keys, $values) ?: [];
        } catch (Exception $e) {
            error_log("Redis MGET error: " . $e->getMessage());
            return [];
        }
    }

    public function setMultiple(array $keyValuePairs, int $ttl = 0): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            if ($ttl > 0) {
                $this->redis->multi();
                foreach ($keyValuePairs as $key => $value) {
                    $this->redis->setex($key, $ttl, $value);
                }
                return $this->redis->exec() !== false;
            } else {
                return $this->redis->mset($keyValuePairs);
            }
        } catch (Exception $e) {
            error_log("Redis MSET error: " . $e->getMessage());
            return false;
        }
    }

    // ================================
    // Cache Tags for Invalidation
    // ================================

    public function tagSet(string $tag, array $keys): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            $tagKey = "tag:{$tag}";
            
            // Redis::sadd() expects individual scalar values
            // Use spread operator to pass array elements as separate arguments
            if (!empty($keys)) {
                // Redis::sadd($key, $member1, $member2, ...)
                return $this->redis->sadd($tagKey, ...$keys) !== false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Redis tag SET error: " . $e->getMessage());
            return false;
        }
    }

    public function tagInvalidate(string $tag): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            $tagKey = "tag:{$tag}";
            $keys = $this->redis->smembers($tagKey);

            if (!empty($keys)) {
                $this->redis->del(array_merge([$tagKey], $keys));
            }

            return true;
        } catch (Exception $e) {
            error_log("Redis tag INVALIDATE error: " . $e->getMessage());
            return false;
        }
    }

    // ================================
    // Session Management
    // ================================

    public function sessionRead(string $sessionId): string {
        if (!$this->ensureConnection()) {
            return '';
        }

        try {
            $data = $this->redis->get("session:{$sessionId}");
            return $data ?: '';
        } catch (Exception $e) {
            error_log("Redis session READ error: " . $e->getMessage());
            return '';
        }
    }

    public function sessionWrite(string $sessionId, string $data): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            // Session TTL: 24 hours
            return $this->redis->setex("session:{$sessionId}", 86400, $data);
        } catch (Exception $e) {
            error_log("Redis session WRITE error: " . $e->getMessage());
            return false;
        }
    }

    public function sessionDestroy(string $sessionId): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            return $this->redis->del("session:{$sessionId}") > 0;
        } catch (Exception $e) {
            error_log("Redis session DESTROY error: " . $e->getMessage());
            return false;
        }
    }

    // ================================
    // Utility Methods
    // ================================

    public function flush(): bool {
        if (!$this->ensureConnection()) {
            return false;
        }

        try {
            return $this->redis->flushdb();
        } catch (Exception $e) {
            error_log("Redis FLUSH error: " . $e->getMessage());
            return false;
        }
    }

    public function getStats(): array {
        if (!$this->ensureConnection()) {
            return ['connected' => false];
        }

        try {
            $info = $this->redis->info();
            return [
                'connected' => true,
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 'unknown',
                'uptime_days' => $info['uptime_in_days'] ?? 'unknown',
                'total_connections_received' => $info['total_connections_received'] ?? 'unknown'
            ];
        } catch (Exception $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get raw Redis instance for advanced operations
     * Use with caution - prefer using the wrapper methods
     */
    public function getRedis(): ?Redis {
        if (!$this->ensureConnection()) {
            return null;
        }
        return $this->redis;
    }

    private function ensureConnection(): bool {
        if (!$this->connected) {
            return $this->connect();
        }
        return true;
    }

    public function __destruct() {
        if ($this->redis && !$this->config['persistent']) {
            $this->redis->close();
        }
    }
}
