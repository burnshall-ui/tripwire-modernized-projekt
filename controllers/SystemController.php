<?php

class SystemController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function resolveSystem(string $requestedSystem): array {
        try {
            // Try to verify system from EVE SDE if available
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
        } catch (PDOException $e) {
            // EVE SDE not available - fall back to default
            // This is expected if EVE Static Data Export is not imported
        }

        // Default to Jita if system not found or EVE SDE unavailable
        return [
            'system' => $requestedSystem ?: 'Jita',
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
