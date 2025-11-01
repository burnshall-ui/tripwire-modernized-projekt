<?php

abstract class ApiController {
    protected PDO $db;
    protected array $userData;

    public function __construct(PDO $db, array $userData = []) {
        $this->db = $db;
        $this->userData = $userData;
    }

    protected function jsonResponse($data, int $statusCode = 200): void {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    protected function errorResponse(string $message, int $statusCode = 400): void {
        $this->jsonResponse(['error' => $message], $statusCode);
    }

    protected function validateRequest(array $requiredFields): bool {
        foreach ($requiredFields as $field) {
            if (!isset($_REQUEST[$field]) || empty($_REQUEST[$field])) {
                $this->errorResponse("Missing required field: {$field}");
                return false;
            }
        }
        return true;
    }

    protected function getValidatedInt(string $field): ?int {
        $value = filter_input(INPUT_REQUEST, $field, FILTER_VALIDATE_INT);
        if ($value === false) {
            $this->errorResponse("Invalid integer value for field: {$field}");
            return null;
        }
        return $value;
    }

    protected function checkPermission(string $maskId): bool {
        // Use the global checkOwner and checkAdmin functions
        return checkOwner($maskId) || checkAdmin($maskId);
    }
}
