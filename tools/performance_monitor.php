<?php

/**
 * Tripwire Performance Monitoring Script
 * Monitors database performance, cache hit rates, and system metrics
 */

require_once('../config.php');
require_once('../db.inc.php');
require_once('../services/DatabaseConnection.php');
require_once('../services/RedisService.php');

class PerformanceMonitor {
    private DatabaseConnection $db;
    private ?RedisService $redis;

    public function __construct() {
        $this->db = DatabaseConnection::getInstance();
        $this->redis = new RedisService();
    }

    public function runAnalysis(): array {
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => $this->analyzeDatabase(),
            'cache' => $this->analyzeCache(),
            'system' => $this->analyzeSystem(),
            'recommendations' => $this->generateRecommendations()
        ];
    }

    private function analyzeDatabase(): array {
        $stats = [];

        try {
            // Connection statistics
            $result = $this->db->querySingle("SHOW STATUS LIKE 'Threads_connected'");
            $stats['active_connections'] = (int)($result['Value'] ?? 0);

            $result = $this->db->querySingle("SHOW STATUS LIKE 'Max_used_connections'");
            $stats['max_connections_used'] = (int)($result['Value'] ?? 0);

            // Query cache statistics
            $result = $this->db->querySingle("SHOW STATUS LIKE 'Qcache_hits'");
            $stats['query_cache_hits'] = (int)($result['Value'] ?? 0);

            $result = $this->db->querySingle("SHOW STATUS LIKE 'Qcache_inserts'");
            $stats['query_cache_inserts'] = (int)($result['Value'] ?? 0);

            $result = $this->db->querySingle("SHOW STATUS LIKE 'Qcache_not_cached'");
            $stats['query_cache_not_cached'] = (int)($result['Value'] ?? 0);

            // Calculate cache hit rate
            $hits = $stats['query_cache_hits'];
            $inserts = $stats['query_cache_inserts'];
            $stats['query_cache_hit_rate'] = $hits + $inserts > 0 ? round(($hits / ($hits + $inserts)) * 100, 2) : 0;

            // Slow queries
            $result = $this->db->querySingle("SHOW STATUS LIKE 'Slow_queries'");
            $stats['slow_queries'] = (int)($result['Value'] ?? 0);

            // InnoDB statistics
            $result = $this->db->querySingle("SHOW STATUS LIKE 'Innodb_buffer_pool_pages_total'");
            $stats['innodb_buffer_pool_pages_total'] = (int)($result['Value'] ?? 0);

            $result = $this->db->querySingle("SHOW STATUS LIKE 'Innodb_buffer_pool_pages_free'");
            $stats['innodb_buffer_pool_pages_free'] = (int)($result['Value'] ?? 0);

            $total = $stats['innodb_buffer_pool_pages_total'];
            $free = $stats['innodb_buffer_pool_pages_free'];
            $stats['innodb_buffer_pool_usage'] = $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0;

            // Table statistics
            $stats['table_stats'] = $this->analyzeTableStats();

        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    private function analyzeTableStats(): array {
        $stats = [];

        try {
            // Analyze most active tables
            $tables = ['signatures', 'wormholes', 'characters', 'userStats'];

            foreach ($tables as $table) {
                $result = $this->db->querySingle("SHOW TABLE STATUS LIKE ?", [':table' => $table]);

                if ($result) {
                    $stats[$table] = [
                        'rows' => (int)($result['Rows'] ?? 0),
                        'data_size' => (int)($result['Data_length'] ?? 0),
                        'index_size' => (int)($result['Index_length'] ?? 0),
                        'engine' => $result['Engine'] ?? 'Unknown'
                    ];
                }
            }

        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    private function analyzeCache(): array {
        if (!$this->redis || !$this->redis->isConnected()) {
            return ['status' => 'disconnected'];
        }

        $stats = $this->redis->getStats();
        $stats['status'] = 'connected';

        // Analyze cache hit rates (if available)
        // This would require additional Redis monitoring

        return $stats;
    }

    private function analyzeSystem(): array {
        $stats = [];

        // PHP memory usage
        $stats['php_memory_usage'] = memory_get_peak_usage(true);
        $stats['php_memory_limit'] = ini_get('memory_limit');

        // System load (if available)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $stats['system_load'] = [
                '1min' => $load[0] ?? 0,
                '5min' => $load[1] ?? 0,
                '15min' => $load[2] ?? 0
            ];
        }

        // Disk usage
        $stats['disk_free'] = disk_free_space('/');
        $stats['disk_total'] = disk_total_space('/');

        return $stats;
    }

    private function generateRecommendations(): array {
        $recommendations = [];
        $dbStats = $this->analyzeDatabase();

        // Database recommendations
        if (($dbStats['query_cache_hit_rate'] ?? 0) < 80) {
            $recommendations[] = "Query cache hit rate is low ({$dbStats['query_cache_hit_rate']}%). Consider increasing query_cache_size.";
        }

        if (($dbStats['innodb_buffer_pool_usage'] ?? 0) > 95) {
            $recommendations[] = "InnoDB buffer pool is nearly full ({$dbStats['innodb_buffer_pool_usage']}%). Consider increasing innodb_buffer_pool_size.";
        }

        if (($dbStats['slow_queries'] ?? 0) > 100) {
            $recommendations[] = "High number of slow queries ({$dbStats['slow_queries']}). Check slow query log and optimize queries.";
        }

        // Cache recommendations
        $cacheStats = $this->analyzeCache();
        if ($cacheStats['status'] === 'disconnected') {
            $recommendations[] = "Redis cache is not connected. Check Redis service and configuration.";
        }

        // Default recommendations
        if (empty($recommendations)) {
            $recommendations[] = "All systems operating within normal parameters.";
        }

        return $recommendations;
    }

    public function outputReport(array $analysis): void {
        echo "=== Tripwire Performance Report ===\n";
        echo "Generated: {$analysis['timestamp']}\n\n";

        // Database section
        echo "📊 DATABASE PERFORMANCE:\n";
        $db = $analysis['database'];
        echo "  Active Connections: " . ($db['active_connections'] ?? 'N/A') . "\n";
        echo "  Query Cache Hit Rate: " . ($db['query_cache_hit_rate'] ?? 'N/A') . "%\n";
        echo "  Slow Queries: " . ($db['slow_queries'] ?? 'N/A') . "\n";
        echo "  InnoDB Buffer Usage: " . ($db['innodb_buffer_pool_usage'] ?? 'N/A') . "%\n";

        if (isset($db['table_stats'])) {
            echo "\n  Table Statistics:\n";
            foreach ($db['table_stats'] as $table => $stats) {
                $size = round(($stats['data_size'] + $stats['index_size']) / 1024 / 1024, 2);
                echo "    {$table}: {$stats['rows']} rows, {$size}MB ({$stats['engine']})\n";
            }
        }
        echo "\n";

        // Cache section
        echo "🔄 CACHE PERFORMANCE:\n";
        $cache = $analysis['cache'];
        if ($cache['status'] === 'connected') {
            echo "  Status: Connected\n";
            echo "  Memory Usage: " . ($cache['used_memory'] ?? 'N/A') . "\n";
            echo "  Connected Clients: " . ($cache['connected_clients'] ?? 'N/A') . "\n";
        } else {
            echo "  Status: Disconnected - Check Redis service\n";
        }
        echo "\n";

        // System section
        echo "🖥️  SYSTEM RESOURCES:\n";
        $sys = $analysis['system'];
        $memoryMB = round(($sys['php_memory_usage'] ?? 0) / 1024 / 1024, 2);
        echo "  PHP Memory Usage: {$memoryMB}MB\n";

        if (isset($sys['system_load'])) {
            $load = $sys['system_load'];
            echo "  System Load: {$load['1min']} (1m), {$load['5min']} (5m), {$load['15min']} (15m)\n";
        }

        $diskGB = round(($sys['disk_free'] ?? 0) / 1024 / 1024 / 1024, 2);
        echo "  Free Disk Space: {$diskGB}GB\n";
        echo "\n";

        // Recommendations
        echo "💡 RECOMMENDATIONS:\n";
        foreach ($analysis['recommendations'] as $rec) {
            echo "  • {$rec}\n";
        }
        echo "\n";
    }
}

// Run the analysis
$monitor = new PerformanceMonitor();
$analysis = $monitor->runAnalysis();
$monitor->outputReport($analysis);

// Save to file for historical tracking
$filename = __DIR__ . '/../logs/performance_' . date('Y-m-d_H-i-s') . '.json';
file_put_contents($filename, json_encode($analysis, JSON_PRETTY_PRINT));
echo "Report saved to: {$filename}\n";
