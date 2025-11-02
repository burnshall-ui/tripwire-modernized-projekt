<?php

namespace Tripwire\Services;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LoggerInterface;

class Logger {
    private static ?LoggerInterface $instance = null;

    /**
     * Get singleton logger instance
     */
    public static function getInstance(): LoggerInterface {
        if (self::$instance === null) {
            self::$instance = self::createLogger();
        }
        return self::$instance;
    }

    /**
     * Create and configure the logger
     */
    private static function createLogger(): LoggerInterface {
        $logger = new MonologLogger('tripwire');

        // Log directory
        $logDir = __DIR__ . '/../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Custom formatter
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            "Y-m-d H:i:s",
            true,
            true
        );

        // Development: Log everything to daily rotating files
        if (getenv('APP_ENV') === 'development' || !getenv('APP_ENV')) {
            $debugHandler = new RotatingFileHandler($logDir . '/tripwire.log', 7, MonologLogger::DEBUG);
            $debugHandler->setFormatter($formatter);
            $logger->pushHandler($debugHandler);
        }

        // Production: Only errors to rotating files
        if (getenv('APP_ENV') === 'production') {
            $errorHandler = new RotatingFileHandler($logDir . '/error.log', 30, MonologLogger::ERROR);
            $errorHandler->setFormatter($formatter);
            $logger->pushHandler($errorHandler);

            // Info level to separate file
            $infoHandler = new RotatingFileHandler($logDir . '/info.log', 7, MonologLogger::INFO);
            $infoHandler->setFormatter($formatter);
            $logger->pushHandler($infoHandler);
        }

        return $logger;
    }

    /**
     * Log debug message
     */
    public static function debug(string $message, array $context = []): void {
        self::getInstance()->debug($message, $context);
    }

    /**
     * Log info message
     */
    public static function info(string $message, array $context = []): void {
        self::getInstance()->info($message, $context);
    }

    /**
     * Log warning message
     */
    public static function warning(string $message, array $context = []): void {
        self::getInstance()->warning($message, $context);
    }

    /**
     * Log error message
     */
    public static function error(string $message, array $context = []): void {
        self::getInstance()->error($message, $context);
    }

    /**
     * Log critical error
     */
    public static function critical(string $message, array $context = []): void {
        self::getInstance()->critical($message, $context);
    }
}
