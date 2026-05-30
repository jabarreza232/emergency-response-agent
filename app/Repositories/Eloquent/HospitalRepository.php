<?php

namespace App\Repositories\Eloquent;

use App\Models\Hospital;
use App\Repositories\Contracts\HospitalRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HospitalRepository implements HospitalRepositoryInterface
{
    public function __construct(
        protected Hospital $model
    ) {}

    /**
     * Find nearest hospitals from given coordinates.
     */
    public function findNearest(
        float $latitude, 
        float $longitude, 
        int $limit = 5,
        float $maxDistanceKm = 50.0
    ): Collection {
        $formula = $this->getDistanceFormula($latitude, $longitude);

        return $this->model
            ->active()
            ->select('hospitals.*')
            ->selectRaw("$formula AS distance")
            // PostgreSQL tidak mendukung alias di WHERE/HAVING, gunakan rumusnya kembali
            ->whereRaw("$formula <= ?", [$maxDistanceKm])
            ->orderBy('distance', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find nearest hospitals with emergency units.
     */
    public function findNearestWithEmergencyUnit(
        float $latitude, 
        float $longitude, 
        int $limit = 5,
        float $maxDistanceKm = 50.0
    ): Collection {
        $formula = $this->getDistanceFormula($latitude, $longitude);

        return $this->model
            ->active()
            ->withEmergencyUnit()
            ->select('hospitals.*')
            ->selectRaw("$formula AS distance")
            // Gunakan whereRaw menggantikan having
            ->whereRaw("$formula <= ?", [$maxDistanceKm])
            ->orderBy('distance', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get Haversine formula for distance calculation.
     * Menggunakan binding placeholder (?) untuk keamanan SQL Injection
     */
    protected function getDistanceFormula(float $latitude, float $longitude): string
    {
        // Kita gunakan sprintf hanya untuk menyusun string, 
        // tapi pastikan angka koordinat diformat dengan benar (float)
        return sprintf(
            '(6371 * acos(cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude))))',
            $latitude,
            $longitude,
            $latitude
        );
    }
}