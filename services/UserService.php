<?php

namespace Tripwire\Services;

use PDO;
use Exception;

class UserService {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function trackUserActivity(int $userId): void {
        $query = 'UPDATE userStats SET systemsViewed = systemsViewed + 1 WHERE userID = :userID';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userID', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getUserData(): array {
        if (!isset($_SESSION['userID'])) {
            return [];
        }

        return [
            'userID' => $_SESSION['userID'] ?? null,
            'characterID' => $_SESSION['characterID'] ?? null,
            'characterName' => $_SESSION['characterName'] ?? null,
            'corporationID' => $_SESSION['corporationID'] ?? null,
            'corporationName' => $_SESSION['corporationName'] ?? null,
            'admin' => $_SESSION['admin'] ?? 0,
            'mask' => $_SESSION['mask'] ?? null
        ];
    }

    public function checkAdminPermission(string $mask): bool {
        $userData = $this->getUserData();

        if (empty($userData) || !isset($userData['corporationID'])) {
            return false;
        }

        if ($mask == $userData['corporationID'] . '.2' && $userData['admin'] == 1) {
            return true;
        }

        $query = 'SELECT corporationID FROM characters INNER JOIN masks ON ownerID = corporationID AND ownerType = 2
                  WHERE characterID = :characterID AND admin = 1 AND maskID = :mask';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':characterID', $userData['characterID'], PDO::PARAM_INT);
        $stmt->bindValue(':mask', $mask);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function checkOwnerPermission(string $mask): bool {
        $userData = $this->getUserData();

        if (empty($userData) || !isset($userData['characterID'])) {
            return false;
        }

        if ($mask == $userData['characterID'] . '.1') {
            return true;
        }

        $query = 'SELECT maskID FROM masks WHERE ownerID = :ownerID AND ownerType = 1373 AND maskID = :mask';
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':ownerID', $userData['characterID'], PDO::PARAM_INT);
        $stmt->bindValue(':mask', $mask);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
