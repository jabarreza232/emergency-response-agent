<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface HospitalRepositoryInterface
{
    /**
     * Find nearest hospitals from given coordinates.
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $limit
     * @param float $maxDistanceKm
     * @return Collection
     */
    public function findNearest(
        float $latitude, 
        float $longitude, 
        int $limit = 5,
        float $maxDistanceKm = 50.0
    ): Collection;

    /**
     * Find nearest hospitals with emergency units.
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $limit
     * @param float $maxDistanceKm
     * @return Collection
     */
    public function findNearestWithEmergencyUnit(
        float $latitude, 
        float $longitude, 
        int $limit = 5,
        float $maxDistanceKm = 50.0
    ): Collection;
}