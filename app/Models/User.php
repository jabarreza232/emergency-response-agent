<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'last_latitude',
        'last_longitude',
        'last_location_updated_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_location_updated_at' => 'datetime',
            'password' => 'hashed',
            'last_latitude' => 'decimal:7',
            'last_longitude' => 'decimal:7',
        ];
    }

    /**
     * Get all emergency contacts for the user.
     */
    public function emergencyContacts()
    {
        return $this->hasMany(EmergencyContact::class)
            ->where('is_active', true)
            ->orderBy('priority', 'asc');
    }

    /**
     * Get all emergency logs for the user.
     */
    public function emergencyLogs()
    {
        return $this->hasMany(EmergencyLog::class)
            ->orderBy('triggered_at', 'desc');
    }

    /**
     * Update user's last known location.
     */
    public function updateLocation(float $latitude, float $longitude): void
    {
        $this->update([
            'last_latitude' => $latitude,
            'last_longitude' => $longitude,
            'last_location_updated_at' => now(),
        ]);
    }

    /**
     * Check if user has a recent location.
     */
    public function hasRecentLocation(int $minutesThreshold = 30): bool
    {
        if (!$this->last_location_updated_at) {
            return false;
        }

        return $this->last_location_updated_at->diffInMinutes(now()) <= $minutesThreshold;
    }
}
