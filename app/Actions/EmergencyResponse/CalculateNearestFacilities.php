<?php

namespace App\Actions\EmergencyResponse;

use App\DTOs\EmergencyRequestDTO;
use App\Services\GeolocationService;
use Illuminate\Support\Collection;

/**
 * Action: Calculate Nearest Facilities
 * 
 * Single-purpose action to find and calculate nearest emergency facilities.
 */
class CalculateNearestFacilities
{
    public function __construct(
        protected GeolocationService $geolocationService
    ) {}

    /**
     * Execute the action.
     */
    public function execute(EmergencyRequestDTO $request): Collection
    {
        return $this->geolocationService->findNearestFacilities(
            latitude: $request->latitude,
            longitude: $request->longitude,
            maxResults: $request->maxHospitals,
            maxDistanceKm: $request->maxDistanceKm
        );
    }
}