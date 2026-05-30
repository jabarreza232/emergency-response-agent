<?php

namespace App\DTOs;

use App\Models\Hospital;

class FacilityDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $address,
        public readonly string $phone,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly float $distance,
        public readonly string $type,
        public readonly bool $hasEmergencyUnit,
        public readonly ?string $operatingHours = null,
        public readonly ?array $facilities = null,
    ) {}

    /**
     * Create DTO from Hospital model with distance.
     */
    public static function fromHospital(Hospital $hospital, float $distance): self
    {
        return new self(
            id: $hospital->id,
            name: $hospital->name,
            address: $hospital->address,
            phone: $hospital->phone,
            latitude: (float) $hospital->latitude,
            longitude: (float) $hospital->longitude,
            distance: round($distance, 2),
            type: $hospital->type,
            hasEmergencyUnit: $hospital->has_emergency_unit,
            operatingHours: $hospital->operating_hours,
            facilities: $hospital->facilities,
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance' => $this->distance,
            'distance_formatted' => $this->distance . ' km',
            'type' => $this->type,
            'has_emergency_unit' => $this->hasEmergencyUnit,
            'operating_hours' => $this->operatingHours,
            'facilities' => $this->facilities,
        ];
    }
}