<?php

namespace Tripwire\Controllers;

use PDO;
use Exception;

class SystemController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function resolveSystem(string $requestedSystem): array {
        // Verify correct system otherwise goto default...
        $query = 'SELECT solarSystemName, systems.solarSystemID, regionName, regions.regionID
                  FROM ' . EVE_DUMP . '.mapSolarSystems systems
                  LEFT JOIN ' . EVE_DUMP . '.mapRegions regions ON regions.regionID = systems.regionID
                  WHERE solarSystemName = :system';

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':system', $requestedSystem);
        $stmt->execute();

        if ($row = $stmt->fetchObject()) {
            return [
                'system' => $row->solarSystemName,
                'systemID' => $row->solarSystemID,
                'region' => $row->regionName,
                'regionID' => $row->regionID
            ];
        }

        // Default to Jita
        return [
            'system' => 'Jita',
            'systemID' => '30000142',
            'region' => 'The Forge',
            'regionID' => 10000002
        ];
    }

    public function trackUserActivity(int $userId): void {
        $query = 'UPDATE userStats SET systemsViewed = systemsViewed + 1 WHERE userID = :userID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userID', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
