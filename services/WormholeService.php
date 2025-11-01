<?php

class WormholeService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getByMask(string $maskId): array {
        $query = 'SELECT * FROM wormholes WHERE maskID = :maskID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':maskID', $maskId);
        $stmt->execute();

        $wormholes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $wormholes[] = new Wormhole($row);
        }

        return $wormholes;
    }

    public function getBySystem(int $systemId, string $maskId): array {
        $query = 'SELECT * FROM wormholes WHERE (fromSystemID = :systemID OR toSystemID = :systemID) AND maskID = :maskID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':systemID', $systemId, PDO::PARAM_INT);
        $stmt->bindValue(':maskID', $maskId);
        $stmt->execute();

        $wormholes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $wormholes[] = new Wormhole($row);
        }

        return $wormholes;
    }

    public function create(array $data): Wormhole {
        $query = 'INSERT INTO wormholes (fromSystemID, toSystemID, signatureID, type, life, mass, createdBy, createdByName, createdTime, modifiedTime, maskID)
                  VALUES (:fromSystemID, :toSystemID, :signatureID, :type, :life, :mass, :createdBy, :createdByName, :createdTime, :modifiedTime, :maskID)';

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':fromSystemID', $data['fromSystemID'], PDO::PARAM_INT);
        $stmt->bindValue(':toSystemID', $data['toSystemID'], PDO::PARAM_INT);
        $stmt->bindValue(':signatureID', $data['signatureID']);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':life', $data['life'], PDO::PARAM_STR);
        $stmt->bindValue(':mass', $data['mass'], PDO::PARAM_INT);
        $stmt->bindValue(':createdBy', $data['createdBy'] ?? null, PDO::PARAM_INT);
        $stmt->bindValue(':createdByName', $data['createdByName'] ?? null);
        $stmt->bindValue(':createdTime', $data['createdTime']);
        $stmt->bindValue(':modifiedTime', $data['modifiedTime']);
        $stmt->bindValue(':maskID', $data['maskID']);
        $stmt->execute();

        $data['id'] = $this->db->lastInsertId();
        return new Wormhole($data);
    }

    public function update(int $id, array $data): bool {
        $query = 'UPDATE wormholes SET
                  signatureID = :signatureID,
                  type = :type,
                  life = :life,
                  mass = :mass,
                  modifiedTime = :modifiedTime
                  WHERE id = :id AND maskID = :maskID';

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':signatureID', $data['signatureID']);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':life', $data['life'], PDO::PARAM_STR);
        $stmt->bindValue(':mass', $data['mass'], PDO::PARAM_INT);
        $stmt->bindValue(':modifiedTime', $data['modifiedTime']);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':maskID', $data['maskID']);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, string $maskId): bool {
        $query = 'DELETE FROM wormholes WHERE id = :id AND maskID = :maskID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':maskID', $maskId);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function getCriticalWormholes(string $maskId): array {
        $query = 'SELECT * FROM wormholes WHERE (life < 0.1 OR mass > 500000000) AND maskID = :maskID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':maskID', $maskId);
        $stmt->execute();

        $wormholes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $wormholes[] = new Wormhole($row);
        }

        return $wormholes;
    }

    public function getWormholeTypes(): array {
        // This would typically come from a static data table or configuration
        return [
            'B274' => ['name' => 'B274 (HS->C1)', 'maxMass' => 500000000, 'lifetime' => 1440],
            'C140' => ['name' => 'C140 (HS->C2)', 'maxMass' => 1000000000, 'lifetime' => 1440],
            'D845' => ['name' => 'D845 (HS->C3)', 'maxMass' => 2000000000, 'lifetime' => 1440],
            'H900' => ['name' => 'H900 (HS->C4)', 'maxMass' => 3000000000, 'lifetime' => 1440],
            'N110' => ['name' => 'N110 (LS->C1)', 'maxMass' => 500000000, 'lifetime' => 1440],
            'N770' => ['name' => 'N770 (LS->C2)', 'maxMass' => 1000000000, 'lifetime' => 1440],
            'O883' => ['name' => 'O883 (LS->C3)', 'maxMass' => 2000000000, 'lifetime' => 1440],
            'S804' => ['name' => 'S804 (NS->C1)', 'maxMass' => 500000000, 'lifetime' => 1440],
            'U210' => ['name' => 'U210 (NS->C2)', 'maxMass' => 1000000000, 'lifetime' => 1440],
            'V301' => ['name' => 'V301 (NS->C3)', 'maxMass' => 2000000000, 'lifetime' => 1440],
            'X702' => ['name' => 'X702 (NS->C4)', 'maxMass' => 3000000000, 'lifetime' => 1440],
        ];
    }

    public function broadcastUpdate(string $maskId, int $systemId, array $wormhole): void {
        // Broadcast to WebSocket clients
        if (class_exists('TripwireWebSocket')) {
            $wsServer = TripwireWebSocket::getInstance();
            $wsServer->broadcastUpdate($maskId, $systemId, 'wormhole', $wormhole);
        }
    }
}
