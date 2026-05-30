<?php

namespace App\DTOs;

class EmergencyRequestDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?string $emergencyType = null,
        public readonly ?string $notes = null,
        public readonly int $maxHospitals = 5,
        public readonly float $maxDistanceKm = 50.0,
    ) {}

    /**
     * Create DTO from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            latitude: $data['latitude'],
            longitude: $data['longitude'],
            emergencyType: $data['emergency_type'] ?? null,
            notes: $data['notes'] ?? null,
            maxHospitals: $data['max_hospitals'] ?? 5,
            maxDistanceKm: $data['max_distance_km'] ?? 50.0,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'emergency_type' => $this->emergencyType,
            'notes' => $this->notes,
            'max_hospitals' => $this->maxHospitals,
            'max_distance_km' => $this->maxDistanceKm,
        ];
    }
}