<?php

/**
 * Security Helper Class
 * Provides input validation and sanitization functions
 */
class SecurityHelper {

    /**
     * Validate and sanitize integer input
     *
     * @param mixed $value The value to validate
     * @param string $paramName Parameter name for error messages
     * @param bool $required Whether the parameter is required
     * @return int|null The validated integer or null if not required and empty
     * @throws InvalidArgumentException If validation fails
     */
    public static function validateInt($value, string $paramName, bool $required = true): ?int {
        if ($value === null || $value === '') {
            if ($required) {
                throw new InvalidArgumentException("Parameter '{$paramName}' is required");
            }
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        if ($filtered === false) {
            throw new InvalidArgumentException("Parameter '{$paramName}' must be a valid integer");
        }

        return $filtered;
    }

    /**
     * Validate and sanitize string input
     *
     * @param mixed $value The value to validate
     * @param string $paramName Parameter name for error messages
     * @param bool $required Whether the parameter is required
     * @param int $maxLength Maximum allowed length
     * @return string|null The validated string or null if not required and empty
     * @throws InvalidArgumentException If validation fails
     */
    public static function validateString($value, string $paramName, bool $required = true, int $maxLength = 255): ?string {
        if ($value === null || $value === '') {
            if ($required) {
                throw new InvalidArgumentException("Parameter '{$paramName}' is required");
            }
            return null;
        }

        $filtered = filter_var($value, FILTER_SANITIZE_STRING);

        if (strlen($filtered) > $maxLength) {
            throw new InvalidArgumentException("Parameter '{$paramName}' exceeds maximum length of {$maxLength}");
        }

        return $filtered;
    }

    /**
     * Validate and sanitize alphanumeric string (for IDs, signatures, etc)
     *
     * @param mixed $value The value to validate
     * @param string $paramName Parameter name for error messages
     * @param bool $required Whether the parameter is required
     * @param int $maxLength Maximum allowed length
     * @return string|null The validated string or null if not required and empty
     * @throws InvalidArgumentException If validation fails
     */
    public static function validateAlphanumeric($value, string $paramName, bool $required = true, int $maxLength = 50): ?string {
        if ($value === null || $value === '') {
            if ($required) {
                throw new InvalidArgumentException("Parameter '{$paramName}' is required");
            }
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException("Parameter '{$paramName}' must be a string");
        }

        // Allow alphanumeric, dash, underscore
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            throw new InvalidArgumentException("Parameter '{$paramName}' must be alphanumeric");
        }

        if (strlen($value) > $maxLength) {
            throw new InvalidArgumentException("Parameter '{$paramName}' exceeds maximum length of {$maxLength}");
        }

        return $value;
    }

    /**
     * Validate enum value against allowed values
     *
     * @param mixed $value The value to validate
     * @param array $allowedValues Array of allowed values
     * @param string $paramName Parameter name for error messages
     * @param bool $required Whether the parameter is required
     * @return string|null The validated value or null if not required and empty
     * @throws InvalidArgumentException If validation fails
     */
    public static function validateEnum($value, array $allowedValues, string $paramName, bool $required = true): ?string {
        if ($value === null || $value === '') {
            if ($required) {
                throw new InvalidArgumentException("Parameter '{$paramName}' is required");
            }
            return null;
        }

        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            throw new InvalidArgumentException("Parameter '{$paramName}' must be one of: {$allowed}");
        }

        return $value;
    }

    /**
     * Get validated integer from request (GET/POST/REQUEST)
     *
     * @param string $key The parameter name
     * @param int $type INPUT_GET, INPUT_POST, or INPUT_REQUEST
     * @param bool $required Whether the parameter is required
     * @return int|null
     * @throws InvalidArgumentException If validation fails
     */
    public static function getInt(string $key, int $type = INPUT_REQUEST, bool $required = true): ?int {
        $value = null;

        if ($type === INPUT_REQUEST) {
            $value = $_REQUEST[$key] ?? null;
        } elseif ($type === INPUT_GET) {
            $value = $_GET[$key] ?? null;
        } elseif ($type === INPUT_POST) {
            $value = $_POST[$key] ?? null;
        }

        return self::validateInt($value, $key, $required);
    }

    /**
     * Get validated string from request (GET/POST/REQUEST)
     *
     * @param string $key The parameter name
     * @param int $type INPUT_GET, INPUT_POST, or INPUT_REQUEST
     * @param bool $required Whether the parameter is required
     * @param int $maxLength Maximum allowed length
     * @return string|null
     * @throws InvalidArgumentException If validation fails
     */
    public static function getString(string $key, int $type = INPUT_REQUEST, bool $required = true, int $maxLength = 255): ?string {
        $value = null;

        if ($type === INPUT_REQUEST) {
            $value = $_REQUEST[$key] ?? null;
        } elseif ($type === INPUT_GET) {
            $value = $_GET[$key] ?? null;
        } elseif ($type === INPUT_POST) {
            $value = $_POST[$key] ?? null;
        }

        return self::validateString($value, $key, $required, $maxLength);
    }

    /**
     * Escape HTML output to prevent XSS
     *
     * @param string|null $value The value to escape
     * @param int $flags htmlspecialchars flags
     * @param string $encoding Character encoding
     * @return string The escaped string
     */
    public static function escapeHtml(?string $value, int $flags = ENT_QUOTES | ENT_HTML5, string $encoding = 'UTF-8'): string {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, $flags, $encoding);
    }

    /**
     * Escape for JavaScript context
     *
     * @param string|null $value The value to escape
     * @return string The escaped string
     */
    public static function escapeJs(?string $value): string {
        if ($value === null) {
            return '';
        }
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Generate CSRF token
     *
     * @return string The CSRF token
     */
    public static function generateCsrfToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     *
     * @param string $token The token to verify
     * @return bool True if valid, false otherwise
     */
    public static function verifyCsrfToken(string $token): bool {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
