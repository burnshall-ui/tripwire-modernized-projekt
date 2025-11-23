<?php

class Wormhole {
    public int $id;
    public int $fromSystemID;
    public int $toSystemID;
    public string $signatureID;
    public string $type;
    public string|float $life;
    public string|int $mass;
    public ?int $createdBy;
    public ?string $createdByName;
    public DateTime $createdTime;
    public DateTime $modifiedTime;
    public string $maskID;

    public function __construct(array $data = []) {
        // Map database column names to model properties
        if (isset($data['initialID'])) {
            $data['fromSystemID'] = $data['initialID'];
        }
        if (isset($data['secondaryID'])) {
            $data['toSystemID'] = $data['secondaryID'];
        }
        
        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }

            if (in_array($key, ['createdTime', 'modifiedTime'])) {
                $this->$key = new DateTime($value);
                continue;
            }

            if ($key === 'life') {
                $this->life = is_numeric($value) ? (float)$value : (string)$value;
                continue;
            }

            if ($key === 'mass') {
                $this->mass = is_numeric($value) ? (int)$value : (string)$value;
                continue;
            }

            $this->$key = $value;
        }
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'initialID' => $this->fromSystemID,
            'secondaryID' => $this->toSystemID,
            'fromSystemID' => $this->fromSystemID,
            'toSystemID' => $this->toSystemID,
            'signatureID' => $this->signatureID,
            'type' => $this->type,
            'life' => $this->life,
            'mass' => $this->mass,
            'createdBy' => $this->createdBy,
            'createdByName' => $this->createdByName,
            'createdTime' => $this->createdTime->format('Y-m-d H:i:s e'),
            'modifiedTime' => $this->modifiedTime->format('Y-m-d H:i:s e'),
            'maskID' => $this->maskID
        ];
    }

    public function getStability(): string {
        if (is_string($this->life)) {
            return strtolower($this->life);
        }

        $life = $this->normalizeLifeValue();

        if ($life < 0.1) {
            return 'critical';
        }
        if ($life < 0.5) {
            return 'warning';
        }
        return 'stable';
    }

    public function getMassStatus(): string {
        if (is_string($this->mass)) {
            return strtolower($this->mass);
        }

        $mass = $this->normalizeMassValue();

        if ($mass > 500000000) {
            return 'critical'; // 500M mass
        }
        if ($mass > 200000000) {
            return 'warning';  // 200M mass
        }
        return 'stable';
    }

    public function isCritical(): bool {
        if (is_string($this->life) && strtolower($this->life) === 'critical') {
            return true;
        }

        if (is_string($this->mass) && strtolower($this->mass) === 'critical') {
            return true;
        }

        return $this->normalizeLifeValue() < 0.1 || $this->normalizeMassValue() > 500000000;
    }

    private function normalizeLifeValue(): float {
        if (is_numeric($this->life)) {
            return (float)$this->life;
        }

        return match (strtolower((string)$this->life)) {
            'critical' => 0.05,
            'warning' => 0.4,
            default => 1.0,
        };
    }

    private function normalizeMassValue(): int {
        if (is_numeric($this->mass)) {
            return (int)$this->mass;
        }

        return match (strtolower((string)$this->mass)) {
            'critical' => 600000000,
            'destab', 'warning' => 300000000,
            default => 0,
        };
    }
}
