<?php

class Wormhole {
    public int $id;
    public int $fromSystemID;
    public int $toSystemID;
    public string $signatureID;
    public string $type;
    public float $life;
    public int $mass;
    public ?int $createdBy;
    public ?string $createdByName;
    public DateTime $createdTime;
    public DateTime $modifiedTime;
    public string $maskID;

    public function __construct(array $data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                if (in_array($key, ['createdTime', 'modifiedTime'])) {
                    $this->$key = new DateTime($value);
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
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
        if ($this->life < 0.1) return 'critical';
        if ($this->life < 0.5) return 'warning';
        return 'stable';
    }

    public function getMassStatus(): string {
        if ($this->mass > 500000000) return 'critical'; // 500M mass
        if ($this->mass > 200000000) return 'warning';  // 200M mass
        return 'stable';
    }

    public function isCritical(): bool {
        return $this->life < 0.1 || $this->mass > 500000000;
    }
}
