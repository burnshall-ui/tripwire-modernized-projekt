<?php

require_once('../../services/SignatureService.php');
require_once('ApiController.php');

class SignaturesApi extends ApiController {
    private SignatureService $signatureService;
    private ?RedisService $redis;

    public function __construct(PDO $db, array $userData = []) {
        parent::__construct($db, $userData);
        $this->redis = new RedisService();
        $this->signatureService = new SignatureService($db, $this->redis);
    }

    private function invalidateCaches(int $systemId, string $maskId): void {
        if ($this->redis && $this->redis->isConnected()) {
            // Invalidate system and mask specific caches
            $this->redis->tagInvalidate("system:{$systemId}");
            $this->redis->tagInvalidate("mask:{$maskId}");
        }
    }

    public function handleRequest(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_SERVER['REQUEST_URI'];

        // Extract maskId from path or query
        $maskId = $_REQUEST['maskId'] ?? '';

        if (!$this->checkPermission($maskId)) {
            $this->errorResponse('Access denied', 403);
            return;
        }

        switch ($method) {
            case 'GET':
                $this->handleGet($maskId);
                break;
            case 'POST':
                $this->handlePost($maskId);
                break;
            case 'PUT':
                $this->handlePut($maskId);
                break;
            case 'DELETE':
                $this->handleDelete($maskId);
                break;
            default:
                $this->errorResponse('Method not allowed', 405);
        }
    }

    private function handleGet(string $maskId): void {
        $systemId = $this->getValidatedInt('systemId');

        try {
            if ($systemId) {
                $signatures = $this->signatureService->getBySystem($systemId, $maskId);
            } else {
                $signatures = $this->signatureService->getByMask($maskId);
            }

            $response = array_map(function($signature) {
                return $signature->toArray();
            }, $signatures);

            $this->jsonResponse($response);
        } catch (Exception $e) {
            $this->errorResponse('Database error: ' . $e->getMessage(), 500);
        }
    }

    private function handlePost(string $maskId): void {
        $requiredFields = ['systemID', 'signatureID', 'type', 'name'];
        if (!$this->validateRequest($requiredFields)) {
            return;
        }

        $data = [
            'systemID' => $this->getValidatedInt('systemID'),
            'signatureID' => trim($_REQUEST['signatureID']),
            'type' => trim($_REQUEST['type']),
            'name' => trim($_REQUEST['name']),
            'description' => trim($_REQUEST['description'] ?? ''),
            'createdBy' => $this->userData['characterID'] ?? null,
            'createdByName' => $this->userData['characterName'] ?? null,
            'lifeTime' => date('Y-m-d H:i:s'),
            'lifeLeft' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'modifiedTime' => date('Y-m-d H:i:s'),
            'maskID' => $maskId
        ];

        try {
            $signature = $this->signatureService->create($data);

            // Invalidate caches
            $this->invalidateCaches($data['systemID'], $maskId);

            // Broadcast update via WebSocket
            $this->signatureService->broadcastUpdate($maskId, $data['systemID'], $signature->toArray());

            $this->jsonResponse($signature->toArray(), 201);
        } catch (Exception $e) {
            $this->errorResponse('Failed to create signature: ' . $e->getMessage(), 500);
        }
    }

    private function handlePut(string $maskId): void {
        $id = $this->getValidatedInt('id');
        if (!$id) return;

        $data = [
            'signatureID' => trim($_REQUEST['signatureID'] ?? ''),
            'type' => trim($_REQUEST['type'] ?? ''),
            'name' => trim($_REQUEST['name'] ?? ''),
            'description' => trim($_REQUEST['description'] ?? ''),
            'lifeTime' => $_REQUEST['lifeTime'] ?? date('Y-m-d H:i:s'),
            'lifeLeft' => $_REQUEST['lifeLeft'] ?? date('Y-m-d H:i:s', strtotime('+24 hours')),
            'modifiedTime' => date('Y-m-d H:i:s'),
            'maskID' => $maskId
        ];

        try {
            $success = $this->signatureService->update($id, $data);
            if ($success) {
                // Invalidate caches
                $this->invalidateCaches($data['systemID'], $maskId);

                // Get updated signature for broadcasting
                $signatures = $this->signatureService->getBySystem($data['systemID'], $maskId);
                $updatedSignature = null;
                foreach ($signatures as $sig) {
                    if ($sig->id == $id) {
                        $updatedSignature = $sig;
                        break;
                    }
                }

                if ($updatedSignature) {
                    $this->signatureService->broadcastUpdate($maskId, $data['systemID'], $updatedSignature->toArray());
                }

                $this->jsonResponse(['success' => true]);
            } else {
                $this->errorResponse('Signature not found or access denied', 404);
            }
        } catch (Exception $e) {
            $this->errorResponse('Failed to update signature: ' . $e->getMessage(), 500);
        }
    }

    private function handleDelete(string $maskId): void {
        $id = $this->getValidatedInt('id');
        if (!$id) return;

        try {
            // Get signature info before deletion for broadcasting
            $signatures = $this->signatureService->getByMask($maskId);
            $deletedSignature = null;
            foreach ($signatures as $sig) {
                if ($sig->id == $id) {
                    $deletedSignature = $sig;
                    break;
                }
            }

            $success = $this->signatureService->delete($id, $maskId);
            if ($success) {
                // Invalidate caches
                if ($deletedSignature) {
                    $this->invalidateCaches($deletedSignature->systemID, $maskId);
                }

                // Broadcast deletion
                if ($deletedSignature) {
                    $deletedData = $deletedSignature->toArray();
                    $deletedData['deleted'] = true;
                    $this->signatureService->broadcastUpdate($maskId, $deletedSignature->systemID, $deletedData);
                }

                $this->jsonResponse(['success' => true]);
            } else {
                $this->errorResponse('Signature not found or access denied', 404);
            }
        } catch (Exception $e) {
            $this->errorResponse('Failed to delete signature: ' . $e->getMessage(), 500);
        }
    }
}

// Handle the request
if (!isset($mysql) || !isset($_SESSION)) {
    require_once('../../config.php');
    require_once('../../db.inc.php');
    require_once('../../lib.inc.php');

    if (!session_id()) {
        session_start([
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'use_only_cookies' => true
        ]);
    }
}

$userData = [
    'characterID' => $_SESSION['characterID'] ?? null,
    'characterName' => $_SESSION['characterName'] ?? null,
    'userID' => $_SESSION['userID'] ?? null
];

$api = new SignaturesApi($mysql, $userData);
$api->handleRequest();
