<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Hospital extends Model
{
  use HasFactory;
 
    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'type',
        'has_emergency_unit',
        'operating_hours',
        'facilities',
        'is_active',
    ];
 
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'has_emergency_unit' => 'boolean',
            'is_active' => 'boolean',
            'facilities' => 'array',
        ];
    }
 
    /**
     * Scope to get only active hospitals.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
 
    /**
     * Scope to get hospitals with emergency units.
     */
    public function scopeWithEmergencyUnit($query)
    {
        return $query->where('has_emergency_unit', true);
    }
 
    /**
     * Calculate distance from given coordinates (in kilometers).
     * Using Haversine formula.
     */
    public function distanceFrom(float $latitude, float $longitude): float
    {
        $earthRadius = 6371; // km
 
        $latFrom = deg2rad($latitude);
        $lonFrom = deg2rad($longitude);
        $latTo = deg2rad($this->latitude);
        $lonTo = deg2rad($this->longitude);
 
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
 
        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));
 
        return $angle * $earthRadius;
    }   //
}
