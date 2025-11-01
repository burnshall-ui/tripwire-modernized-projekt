<?php

class Signature {
    public int $id;
    public int $systemID;
    public string $signatureID;
    public string $type;
    public string $name;
    public ?string $description;
    public ?int $createdBy;
    public ?string $createdByName;
    public DateTime $lifeTime;
    public DateTime $lifeLeft;
    public DateTime $modifiedTime;
    public string $maskID;

    public function __construct(array $data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                if (in_array($key, ['lifeTime', 'lifeLeft', 'modifiedTime'])) {
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
            'systemID' => $this->systemID,
            'signatureID' => $this->signatureID,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'createdBy' => $this->createdBy,
            'createdByName' => $this->createdByName,
            'lifeTime' => $this->lifeTime->format('Y-m-d H:i:s e'),
            'lifeLeft' => $this->lifeLeft->format('Y-m-d H:i:s e'),
            'modifiedTime' => $this->modifiedTime->format('Y-m-d H:i:s e'),
            'maskID' => $this->maskID
        ];
    }

    public function isExpired(): bool {
        return $this->lifeLeft <= new DateTime();
    }

    public function getTimeToLive(): int {
        $now = new DateTime();
        $interval = $now->diff($this->lifeLeft);
        return $interval->invert ? 0 : $interval->s + ($interval->i * 60) + ($interval->h * 3600) + ($interval->d * 86400);
    }
}
