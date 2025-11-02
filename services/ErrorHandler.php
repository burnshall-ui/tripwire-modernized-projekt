<?php

namespace Tripwire\Services;

use Exception;
use Throwable;
use ErrorException;

class AppException extends Exception {
    protected $httpCode;

    public function __construct(string $message = "", int $code = 0, int $httpCode = 500, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
    }

    public function getHttpCode(): int {
        return $this->httpCode;
    }
}

class ValidationException extends AppException {
    public function __construct(string $message = "Validation failed") {
        parent::__construct($message, 0, 400);
    }
}

class PermissionException extends AppException {
    public function __construct(string $message = "Access denied") {
        parent::__construct($message, 0, 403);
    }
}

class NotFoundException extends AppException {
    public function __construct(string $message = "Resource not found") {
        parent::__construct($message, 0, 404);
    }
}

class ErrorHandler {
    private bool $debug;

    public function __construct(bool $debug = false) {
        $this->debug = $debug;
        $this->registerHandlers();
    }

    private function registerHandlers(): void {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function handleException(Throwable $exception): void {
        $httpCode = 500;
        $message = 'Internal Server Error';

        if ($exception instanceof AppException) {
            $httpCode = $exception->getHttpCode();
            $message = $exception->getMessage();
        }

        $this->sendErrorResponse($httpCode, $message, $exception);
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Convert errors to exceptions for consistent handling
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function handleShutdown(): void {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleException(new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    private function sendErrorResponse(int $httpCode, string $message, ?Throwable $exception = null): void {
        // Don't send headers if already sent
        if (!headers_sent()) {
            http_response_code($httpCode);
            header('Content-Type: application/json');

            // Security headers
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
        }

        $response = [
            'error' => $message,
            'code' => $httpCode
        ];

        if ($this->debug && $exception) {
            $response['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        echo json_encode($response);
        exit;
    }

    public function logError(Throwable $exception): void {
        $logMessage = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n",
            date('Y-m-d H:i:s'),
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        error_log($logMessage, 3, __DIR__ . '/../logs/error.log');
    }
}

// Initialize error handling
function initErrorHandling(): ErrorHandler {
    $debug = defined('DEBUG') && DEBUG === true;
    return new ErrorHandler($debug);
}
