<?php

namespace App\Services;

use App\Models\EmergencyContact;
use App\Models\EmergencyLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\EmergencyAlertNotification;

class NotificationService
{
    /**
     * Notify an emergency contact about an emergency.
     */
    public function notifyEmergencyContact(
        EmergencyContact $contact,
        User $user,
        EmergencyLog $log,
        Collection $facilities
    ): void {
        $nearestFacility = $facilities->first();
        
        // Prepare notification data
        $data = [
            'user_name' => $user->name,
            'emergency_type' => $log->emergency_type ?? 'Emergency Alert',
            'location' => [
                'latitude' => $log->latitude,
                'longitude' => $log->longitude,
            ],
            'google_maps_url' => $this->generateGoogleMapsUrl($log->latitude, $log->longitude),
            'nearest_hospital' => $nearestFacility ? [
                'name' => $nearestFacility->name,
                'address' => $nearestFacility->address,
                'phone' => $nearestFacility->phone,
                'distance' => $nearestFacility->distance . ' km',
            ] : null,
            'timestamp' => $log->triggered_at->format('Y-m-d H:i:s'),
        ];

        // Log notification attempt
        Log::info('Sending emergency notification', [
            'contact_id' => $contact->id,
            'contact_name' => $contact->name,
            'emergency_id' => $log->id,
        ]);

        // In a real implementation, you would send actual SMS/Email here
        // For now, we'll use Laravel's notification system
        
        // Notification::route('mail', $contact->email)
        //     ->notify(new EmergencyAlertNotification($data));
        
        // For demo purposes, just log it
        Log::info('Emergency notification sent', [
            'contact' => $contact->name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'data' => $data,
        ]);
    }

    /**
     * Broadcast emergency alert via Laravel Reverb (real-time).
     */
    public function broadcastEmergencyAlert(User $user, EmergencyLog $log, Collection $facilities): void
    {
        // This would use Laravel Broadcasting with Reverb
        // broadcast(new EmergencyTriggered($user, $log, $facilities));
        
        Log::info('Broadcasting emergency alert', [
            'user_id' => $user->id,
            'emergency_id' => $log->id,
        ]);
    }

    /**
     * Generate Google Maps URL from coordinates.
     */
    protected function generateGoogleMapsUrl(float $latitude, float $longitude): string
    {
        return "https://www.google.com/maps?q={$latitude},{$longitude}";
    }
}