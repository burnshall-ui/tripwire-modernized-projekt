<?php

class DatabaseConnection {
    private static ?DatabaseConnection $instance = null;
    private PDO $pdo;
    private array $preparedStatements = [];
    private array $config;

    private function __construct(array $config = []) {
        $this->config = array_merge([
            'host' => 'mysql',
            'port' => 3306,
            'database' => 'tripwire',
            'username' => 'tripwire',
            'password' => 'tripwirepass',
            'charset' => 'utf8mb4',
            'persistent' => true,
            'pool_size' => 5,
        ], $config);

        $this->connect();
    }

    public static function getInstance(array $config = []): DatabaseConnection {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    private function connect(): void {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => $this->config['persistent'],
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], $options);

            // Performance optimizations
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            $this->pdo->exec("SET SESSION innodb_buffer_pool_size = 134217728"); // 128MB
            $this->pdo->exec("SET SESSION innodb_log_file_size = 33554432"); // 32MB
            $this->pdo->exec("SET SESSION query_cache_type = 1");
            $this->pdo->exec("SET SESSION query_cache_size = 67108864"); // 64MB

        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    public function getPDO(): PDO {
        return $this->pdo;
    }

    /**
     * Get or create a prepared statement with caching
     */
    public function prepare(string $query): PDOStatement {
        $queryHash = md5($query);

        if (!isset($this->preparedStatements[$queryHash])) {
            $this->preparedStatements[$queryHash] = $this->pdo->prepare($query);
        }

        return $this->preparedStatements[$queryHash];
    }

    /**
     * Execute a query with optional caching
     */
    public function query(string $query, array $params = [], ?int $cacheTtl = null): array {
        $stmt = $this->prepare($query);

        // Bind parameters
        foreach ($params as $key => $value) {
            $paramType = $this->getParamType($value);
            $stmt->bindValue($key, $value, $paramType);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Execute a single row query
     */
    public function querySingle(string $query, array $params = []) {
        $stmt = $this->prepare($query);

        foreach ($params as $key => $value) {
            $paramType = $this->getParamType($value);
            $stmt->bindValue($key, $value, $paramType);
        }

        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Execute an INSERT/UPDATE/DELETE query
     */
    public function execute(string $query, array $params = []): int {
        $stmt = $this->prepare($query);

        foreach ($params as $key => $value) {
            $paramType = $this->getParamType($value);
            $stmt->bindValue($key, $value, $paramType);
        }

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    /**
     * Start a transaction
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit a transaction
     */
    public function commit(): bool {
        return $this->pdo->commit();
    }

    /**
     * Rollback a transaction
     */
    public function rollback(): bool {
        return $this->pdo->rollBack();
    }

    /**
     * Get database statistics
     */
    public function getStats(): array {
        $stats = [];

        try {
            // Connection info
            $stats['connections'] = $this->querySingle("SHOW STATUS LIKE 'Threads_connected'");

            // Query cache info
            $stats['query_cache'] = $this->querySingle("SHOW STATUS LIKE 'Qcache_hits'");
            $stats['query_cache_misses'] = $this->querySingle("SHOW STATUS LIKE 'Qcache_inserts'");

            // InnoDB buffer pool
            $stats['innodb_buffer'] = $this->querySingle("SHOW STATUS LIKE 'Innodb_buffer_pool_pages_total'");

            // Slow queries
            $stats['slow_queries'] = $this->querySingle("SHOW STATUS LIKE 'Slow_queries'");

            // Prepared statements cache
            $stats['prepared_statements_cached'] = count($this->preparedStatements);

        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Clear prepared statements cache
     */
    public function clearPreparedStatementsCache(): void {
        $this->preparedStatements = [];
    }

    /**
     * Get PDO parameter type from value
     */
    private function getParamType($value): int {
        if (is_int($value)) {
            return PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            return PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            return PDO::PARAM_NULL;
        } else {
            return PDO::PARAM_STR;
        }
    }

    /**
     * Health check
     */
    public function healthCheck(): bool {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
