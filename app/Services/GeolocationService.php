<?php

namespace App\Services;

use App\DTOs\FacilityDTO;
use App\Repositories\Contracts\HospitalRepositoryInterface;
use Illuminate\Support\Collection;

class GeolocationService
{
    public function __construct(
        protected HospitalRepositoryInterface $hospitalRepository
    ) {}

    /**
     * Find nearest facilities from given coordinates.
     *
     * @param float $latitude
     * @param float $longitude
     * @param int $maxResults
     * @param float $maxDistanceKm
     * @return Collection<FacilityDTO>
     */
    public function findNearestFacilities(
        float $latitude,
        float $longitude,
        int $maxResults = 5,
        float $maxDistanceKm = 50.0
    ): Collection {
        $hospitals = $this->hospitalRepository->findNearestWithEmergencyUnit(
            $latitude,
            $longitude,
            $maxResults,
            $maxDistanceKm
        );

        return $hospitals->map(function ($hospital) {
            return FacilityDTO::fromHospital(
                $hospital,
                $hospital->distance ?? 0
            );
        });
    }

    /**
     * Calculate distance between two coordinates (in km).
     * Using Haversine formula.
     */
    public function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371; // km

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return round($angle * $earthRadius, 2);
    }

    /**
     * Validate coordinates.
     */
    public function validateCoordinates(float $latitude, float $longitude): bool
    {
        return $latitude >= -90 && $latitude <= 90 &&
               $longitude >= -180 && $longitude <= 180;
    }
}