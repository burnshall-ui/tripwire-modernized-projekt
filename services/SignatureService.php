<?php

namespace Tripwire\Services;

use PDO;
use Exception;

class SignatureService {
    private PDO $db;
    private ?RedisService $redis;

    public function __construct(PDO $db, ?RedisService $redis = null) {
        $this->db = $db;
        $this->redis = $redis;
    }

    public function getBySystem(int $systemId, string $maskId): array {
        $cacheKey = "signatures:system:{$systemId}:{$maskId}";

        // Try cache first
        if ($this->redis && $this->redis->isConnected()) {
            $cached = $this->redis->get($cacheKey);
            if ($cached !== null) {
                return array_map(fn($data) => new Signature($data), $cached);
            }
        }

        // Query database
        $query = 'SELECT * FROM signatures WHERE (systemID = :systemID OR type = "wormhole") AND maskID = :maskID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':systemID', $systemId, PDO::PARAM_INT);
        $stmt->bindValue(':maskID', $maskId);
        $stmt->execute();

        $signatures = [];
        $rawData = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $signature = new Signature($row);
            $signatures[] = $signature;
            $rawData[] = $row;
        }

        // Cache result for 5 minutes
        if ($this->redis && $this->redis->isConnected()) {
            $this->redis->set($cacheKey, $rawData, 300);
            $this->redis->tagSet("system:{$systemId}", [$cacheKey]);
            $this->redis->tagSet("mask:{$maskId}", [$cacheKey]);
        }

        return $signatures;
    }

    public function getByMask(string $maskId): array {
        $query = 'SELECT * FROM signatures WHERE maskID = :maskID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':maskID', $maskId);
        $stmt->execute();

        $signatures = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $signatures[] = new Signature($row);
        }

        return $signatures;
    }

    public function create(array $data): Signature {
        $query = 'INSERT INTO signatures (systemID, signatureID, type, name, description, createdBy, createdByName, lifeTime, lifeLeft, modifiedTime, maskID)
                  VALUES (:systemID, :signatureID, :type, :name, :description, :createdBy, :createdByName, :lifeTime, :lifeLeft, :modifiedTime, :maskID)';

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':systemID', $data['systemID'], PDO::PARAM_INT);
        $stmt->bindValue(':signatureID', $data['signatureID']);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);
        $stmt->bindValue(':createdBy', $data['createdBy'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':createdByName', $data['createdByName'] ?? null);
        $stmt->bindValue(':lifeTime', $data['lifeTime']);
        $stmt->bindValue(':lifeLeft', $data['lifeLeft']);
        $stmt->bindValue(':modifiedTime', $data['modifiedTime']);
        $stmt->bindValue(':maskID', $data['maskID']);
        $stmt->execute();

        $data['id'] = $this->db->lastInsertId();
        return new Signature($data);
    }

    public function update(int $id, array $data): bool {
        $query = 'UPDATE signatures SET
                  signatureID = :signatureID,
                  type = :type,
                  name = :name,
                  description = :description,
                  lifeTime = :lifeTime,
                  lifeLeft = :lifeLeft,
                  modifiedTime = :modifiedTime
                  WHERE id = :id AND maskID = :maskID';

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':signatureID', $data['signatureID']);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);
        $stmt->bindValue(':lifeTime', $data['lifeTime']);
        $stmt->bindValue(':lifeLeft', $data['lifeLeft']);
        $stmt->bindValue(':modifiedTime', $data['modifiedTime']);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':maskID', $data['maskID']);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $maskId): bool {
        $query = 'DELETE FROM signatures WHERE id = :id AND maskID = :maskID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':maskID', $maskId);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function broadcastUpdate(string $maskId, int $systemId, array $signature): void {
        // Broadcast to WebSocket clients
        if (class_exists('TripwireWebSocket')) {
            $wsServer = TripwireWebSocket::getInstance();
            $wsServer->broadcastUpdate($maskId, $systemId, 'signature', $signature);
        }
    }

    public function getExpiredSignatures(string $maskId): array {
        $now = new DateTime();
        $query = 'SELECT * FROM signatures WHERE lifeLeft <= :now AND maskID = :maskID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':now', $now->format('Y-m-d H:i:s'));
        $stmt->bindValue(':maskID', $maskId);
        $stmt->execute();

        $signatures = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $signatures[] = new Signature($row);
        }

        return $signatures;
    }
}
