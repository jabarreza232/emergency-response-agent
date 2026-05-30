<?php

use Livewire\Volt\Component;
use App\DTOs\EmergencyRequestDTO;
use App\Services\EmergencyResponseService;
use Livewire\Attributes\On;

new class extends Component
{
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?string $emergencyType = null;
    public ?string $notes = null;
    public bool $locationEnabled = false;
    public bool $processing = false;
    public ?array $response = null;
    public ?string $error = null;

    /**
     * Get user's current location from browser.
     */
    public function getLocation(): void
    {
        $this->dispatch('request-geolocation');
    }

    /**
     * Receive location from JavaScript.
     */
    #[On('location-received')]
    public function receiveLocation(float $latitude, float $longitude): void
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->locationEnabled = true;
        $this->error = null;

        // Update user's last known location
        auth()->user()->updateLocation($latitude, $longitude);
    }

    /**
     * Handle location error from JavaScript.
     */
    #[On('location-error')]
    public function locationError(string $error): void
    {
        $this->error = $error;
        $this->locationEnabled = false;
    }

    /**
     * Trigger emergency response.
     */
    public function triggerEmergency(): void
    {
        $this->processing = true;
        $this->error = null;
        $this->response = null;

        try {
            // Validate
            if (!$this->latitude || !$this->longitude) {
                throw new \Exception('Location is required. Please enable location access.');
            }

            // Create request DTO
            $request = new EmergencyRequestDTO(
                userId: auth()->id(),
                latitude: $this->latitude,
                longitude: $this->longitude,
                emergencyType: $this->emergencyType,
                notes: $this->notes,
            );

            // Process emergency through agentic service
            $emergencyService = app(EmergencyResponseService::class);
            $this->response = $emergencyService->processEmergencyTrigger($request);

            // Broadcast real-time notification (if using Reverb)
            // $this->dispatch('emergency-triggered', $this->response);

            // Reset form
            $this->reset(['emergencyType', 'notes']);

        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->processing = false;
        }
    }

    /**
     * Use last known location.
     */
    public function useLastKnownLocation(): void
    {
        $user = auth()->user();
        
        if ($user->hasRecentLocation()) {
            $this->latitude = (float) $user->last_latitude;
            $this->longitude = (float) $user->last_longitude;
            $this->locationEnabled = true;
            $this->error = null;
        } else {
            $this->error = 'No recent location found. Please enable location access.';
        }
    }
}; ?>

<div class="max-w-2xl mx-auto p-6">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-2xl font-bold text-red-600 mb-6">Emergency Response System</h2>

        @if($error)
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ $error }}
            </div>
        @endif

        @if($response)
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <h3 class="font-bold mb-2">Emergency Alert Sent!</h3>
                <p class="text-sm">Emergency ID: #{{ $response['emergency_id'] }}</p>
                <p class="text-sm">Severity: {{ ucfirst($response['severity']) }}</p>
                <p class="text-sm">Contacts Notified: {{ $response['contacts_notified'] }}</p>
                <p class="text-sm">Nearest Facilities Found: {{ count($response['facilities']) }}</p>
            </div>
        @endif

        <!-- Location Status -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700">Location Status</label>
                @if($locationEnabled)
                    <span class="text-green-600 text-sm">✓ Location Enabled</span>
                @else
                    <span class="text-red-600 text-sm">✗ Location Disabled</span>
                @endif
            </div>

            <div class="flex gap-2">
                <button 
                    wire:click="getLocation" 
                    class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>Get Current Location</span>
                    <span wire:loading>Getting Location...</span>
                </button>

                <button 
                    wire:click="useLastKnownLocation" 
                    class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600"
                >
                    Use Last Known Location
                </button>
            </div>

            @if($latitude && $longitude)
                <p class="text-sm text-gray-600 mt-2">
                    Coordinates: {{ number_format($latitude, 6) }}, {{ number_format($longitude, 6) }}
                </p>
            @endif
        </div>

        <!-- Emergency Type -->
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Emergency Type</label>
            <select 
                wire:model="emergencyType" 
                class="w-full border border-gray-300 rounded-md px-3 py-2"
            >
                <option value="">Select emergency type...</option>
                <option value="medical">Medical Emergency</option>
                <option value="accident">Accident</option>
                <option value="cardiac">Cardiac/Heart Issue</option>
                <option value="breathing">Breathing Problem</option>
                <option value="injury">Severe Injury</option>
                <option value="other">Other</option>
            </select>
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
            <textarea 
                wire:model="notes" 
                rows="3" 
                class="w-full border border-gray-300 rounded-md px-3 py-2"
                placeholder="Describe the emergency (optional)..."
            ></textarea>
        </div>

        <!-- Trigger Button -->
        <button 
            wire:click="triggerEmergency" 
            class="w-full bg-red-600 text-white font-bold py-4 px-6 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
            wire:loading.attr="disabled"
            @disabled(!$locationEnabled || $processing)
        >
            @if($processing)
                <span>Processing Emergency...</span>
            @else
                <span>🚨 TRIGGER EMERGENCY RESPONSE</span>
            @endif
        </button>

        @if(!$locationEnabled)
            <p class="text-sm text-gray-600 mt-2 text-center">
                Please enable location to trigger emergency response
            </p>
        @endif
    </div>

    <!-- Geolocation JavaScript -->
    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('request-geolocation', () => {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            Livewire.dispatch('location-received', {
                                latitude: position.coords.latitude,
                                longitude: position.coords.longitude
                            });
                        },
                        (error) => {
                            let errorMessage = 'Unable to get location';
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMessage = 'Location permission denied. Please enable location access.';
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMessage = 'Location information unavailable.';
                                    break;
                                case error.TIMEOUT:
                                    errorMessage = 'Location request timeout.';
                                    break;
                            }
                            Livewire.dispatch('location-error', { error: errorMessage });
                        }
                    );
                } else {
                    Livewire.dispatch('location-error', { 
                        error: 'Geolocation is not supported by this browser.' 
                    });
                }
            });
        });
    </script>
</div>