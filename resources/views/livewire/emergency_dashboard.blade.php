<?php

use Livewire\Volt\Component;
use App\Models\EmergencyLog;
use App\Models\EmergencyContact;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Session;
use App\DTOs\EmergencyRequestDTO;
use App\Services\EmergencyResponseService;
use function Livewire\Volt\{state, on};

// State untuk menyimpan daftar log darurat yang masuk secara real-time

state([]);

// Mendengarkan siaran dari Reverb

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Dashboard State
    public $showContactForm = false;
public $alerts = [];
    // Trigger Form State
    public $showTriggerModal = false;
    public $emergencyType = '';
    public $notes = '';
    public $latitude = null;
    public $longitude = null;
    public $locationEnabled = false;
    public $processing = false;
    public $error = null;
    public $response = null;

    // Contact Form Fields
    public $contact_name = '';
    public $contact_phone = '';
    public $contact_relationship = '';
    public $contact_email = '';
    public $contact_priority = 1;


    public function with(): array
    {
       

        return [
            
            'logs' => EmergencyLog::where('user_id', auth()->id())
                ->with('user')
                ->orderBy('triggered_at', 'desc')
                ->paginate(10),
            'contacts' => EmergencyContact::where('user_id', auth()->id())
                ->orderBy('priority', 'asc')
                ->get(),
            'stats' => [
                'total_emergencies' => EmergencyLog::where('user_id', auth()->id())->count(),
                'active_emergencies' => EmergencyLog::where('user_id', auth()->id())->active()->count(),
                'resolved_emergencies' => EmergencyLog::where('user_id', auth()->id())->resolved()->count(),
            ],
        ];
    }

    // --- Geolocation Logic ---

    public function getLocation()
    {
        $this->dispatch('request-geolocation');
    }


    // Pastikan method menerima parameter $data
    public function handleLocationReceived($data)
    {
        // Livewire akan membungkus payload dalam array jika dikirim dari dispatch
        $this->latitude = $data['latitude'];
        $this->longitude = $data['longitude'];
        $this->locationEnabled = true;
        $this->error = null;
    }

    public function handleLocationError($data)
    {
        $this->error = is_array($data) ? $data['error'] : $data;
        $this->locationEnabled = false;
    }
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

    // --- Original Dashboard Methods ---

    public function resolveEmergency(int $logId)
    {
        $log = EmergencyLog::where('user_id', auth()->id())->findOrFail($logId);
        $log->markAsResolved();
    }

    public function addContact()
    {
        $this->validate([
            'contact_name' => 'required',
            'contact_phone' => 'required',
            'contact_priority' => 'required|integer',
        ]);

        EmergencyContact::create([
            'user_id' => auth()->id(),
            'name' => $this->contact_name,
            'phone' => $this->contact_phone,
            'relationship' => $this->contact_relationship,
            'email' => $this->contact_email,
            'priority' => $this->contact_priority,
            'is_active' => true,
        ]);

        $this->reset(['contact_name', 'contact_phone', 'contact_relationship', 'contact_email', 'contact_priority']);
        $this->showContactForm = false;
    }

    public function deleteContact($id)
    {
        EmergencyContact::where('user_id', auth()->id())->find($id)?->delete();
    }
  protected function getListeners()
    {
        return [
            // Gunakan titik (.) jika di Event Anda pakai broadcastAs()
        'echo:emergency-channel,emergency.triggered' => 'handleIncomingEmergency',
            'location-received' => 'handleLocationReceived',
            'location-error' => 'handleLocationError'
        ];
    }
    public function handleIncomingEmergency($event)
    {
        // Tambahkan data terbaru ke urutan paling atas array
        array_unshift($this->alerts, [
            'type' => $event['type'] ?? $event['emergency_type'] ?? 'Unknown',
            'latitude' => $event['latitude'],
            'longitude' => $event['longitude'],
            'notes' => $event['notes'] ?? ''
        ]);

        // Opsional: Batasi hanya 5 alert terbaru agar tidak memenuhi layar
        if (count($this->alerts) > 5) {
            array_pop($this->alerts);
        }
    }
    public function closeAlert($index)
{
    unset($this->alerts[$index]);
    $this->alerts = array_values($this->alerts); // reset index
}
}; 

?>

<div class="max-w-6xl mx-auto p-6" x-data="{ openTrigger: false }">
    
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Emergency Dashboard</h1>
        <button
            @click="openTrigger = true"
            class="bg-red-600 text-white px-6 py-3 rounded-full font-bold shadow-lg hover:bg-red-700 transition flex items-center gap-2">
            <span class="text-xl">🚨</span> TRIGGER EMERGENCY
        </button>
    </div>
<div class="max-w-6xl mx-auto p-6" x-data="{ openTrigger: false }">
{{-- ALERT REAL-TIME (Pindahkan ke sini agar aman di dalam root) --}}
<div class="fixed top-5 right-5 z-[100] flex flex-col gap-2">
@foreach($alerts as $alert)
<div class="bg-red-600 text-white p-4 rounded-lg shadow-2xl border-2 border-white animate-bounce">
⚠️ **DARURAT BARU!** <br>
Tipe: {{ $alert['type'] }} <br>
<small>Lokasi: {{ $alert['latitude'] }}, {{ $alert['longitude'] }}</small>
</div>
@endforeach
</div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-blue-100 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-blue-800">Total</h3>
            <p class="text-3xl font-bold text-blue-900">{{ $stats['total_emergencies'] }}</p>
        </div>
        <div class="bg-yellow-100 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-yellow-800">Active</h3>
            <p class="text-3xl font-bold text-yellow-900">{{ $stats['active_emergencies'] }}</p>
        </div>
        <div class="bg-green-100 p-4 rounded-lg">
            <h3 class="text-lg font-semibold text-green-800">Resolved</h3>
            <p class="text-3xl font-bold text-green-900">{{ $stats['resolved_emergencies'] }}</p>
        </div>
    </div>

    <div
        x-show="openTrigger"
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
        x-transition>
        <div class="fixed inset-0 bg-black opacity-50"></div>

        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative bg-white rounded-xl shadow-2xl max-w-2xl w-full p-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-red-600">🚨 Emergency System</h2>
                    <button @click="openTrigger = false" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                </div>

                @if($error)
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm">{{ $error }}</div>
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

                <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium">Location Status:</span>
                        <span class="{{ $locationEnabled ? 'text-green-600' : 'text-red-600' }} text-xs font-bold">
                            {{ $locationEnabled ? '✓ ACTIVE' : '✗ INACTIVE' }}
                        </span>
                    </div>
                    <button wire:click="getLocation" class="w-full bg-blue-500 text-white py-2 rounded text-sm mb-2">
                        Get Current Location
                    </button>
                    @if($latitude)
                    <p class="text-[10px] text-gray-500 text-center">Lat: {{ $latitude }}, Lng: {{ $longitude }}</p>
                    @endif
                       @if($latitude && $longitude)
                    <p class="text-sm text-gray-600 mt-2 text-center">
                        Coordinates: {{ number_format($latitude, 6) }}, {{ number_format($longitude, 6) }}
                    </p>
                    @endif
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold mb-1">Emergency Type</label>
                        <select
                            wire:model="emergencyType"
                            class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="">Select emergency type...</option>
                            <option value="medical">Medical Emergency</option>
                            <option value="accident">Accident</option>
                            <option value="cardiac">Cardiac/Heart Issue</option>
                            <option value="breathing">Breathing Problem</option>
                            <option value="injury">Severe Injury</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold mb-1">Notes</label>
                        <textarea wire:model="notes" class="w-full border rounded-md p-2" rows="2"></textarea>
                    </div>

                    <div class="mt-6">
                        <div wire:loading wire:target="triggerEmergency" class="w-full h-2 bg-gray-200 rounded-full overflow-hidden mb-2">
                            <div class="h-full bg-red-600 animate-progress w-full"></div>
                        </div>

                        <button
                            wire:click="triggerEmergency"
                            class="w-full bg-red-600 text-white font-bold py-4 px-6 rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all relative overflow-hidden"
                            wire:loading.attr="disabled"
                            @disabled(!$locationEnabled || $processing)>
                            <div class="flex items-center justify-center gap-2">
                                <svg wire:loading wire:target="triggerEmergency" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>

                                <span>
                                    @if($processing)
                                    PROCESSING EMERGENCY...
                                    @else
                                    🚨 TRIGGER EMERGENCY RESPONSE
                                    @endif
                                </span>
                            </div>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>



    <!-- Emergency Logs Section -->
    <div class="bg-white rounded-lg shadow-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Emergency History</h2>

        <div class="space-y-4">
            @forelse($logs as $log)
            <div class="border rounded-lg p-4 {{ $log->status === 'triggered' ? 'border-red-300 bg-red-50' : 'border-gray-200' }}">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="font-bold text-lg">#{{ $log->id }}</span>
                            <span class="px-2 py-1 rounded text-sm
@if($log->status === 'triggered') bg-red-100 text-red-800
@elseif($log->status === 'contacted') bg-yellow-100 text-yellow-800
@elseif($log->status === 'resolved') bg-green-100 text-green-800
@else bg-gray-100 text-gray-800
@endif
">
                                {{ ucfirst($log->status) }}
                            </span>
                            @if($log->emergency_type)
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">
                                {{ ucfirst($log->emergency_type) }}
                            </span>
                            @endif
                        </div>

                        <p class="text-sm text-gray-600 mb-2">
                            <strong>Triggered:</strong> {{ $log->triggered_at->format('Y-m-d H:i:s') }}
                        </p>

                        <p class="text-sm text-gray-600 mb-2">
                            <strong>Location:</strong> {{ number_format($log->latitude, 6) }}, {{ number_format($log->longitude, 6) }}
                        </p>

                        @if($log->notes)
                        <p class="text-sm text-gray-700 mb-2">
                            <strong>Notes:</strong> {{ $log->notes }}
                        </p>
                        @endif

                        @if($log->response_data)
                        <div class="mt-2 text-xs text-gray-500">
                            <strong>Agent Decision:</strong>
                            Severity: {{ $log->response_data['severity_determined'] ?? 'N/A' }},
                            Facilities: {{ $log->response_data['facilities_found'] ?? 0 }},
                            Distance: {{ $log->response_data['distance_category'] ?? 'N/A' }}
                        </div>
                        @endif
                    </div>

                    @if($log->status !== 'resolved' && $log->status !== 'cancelled')
                    <button
                        wire:click="resolveEmergency({{ $log->id }})"
                        class="ml-4 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 text-sm">
                        Mark as Resolved
                    </button>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-center text-gray-500 py-8">No emergency logs yet</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </div>
</div>


<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('request-geolocation', () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        // Kirim sebagai objek tunggal
                        Livewire.dispatch('location-received', {
                            data: {
                                latitude: position.coords.latitude,
                                longitude: position.coords.longitude
                            }
                        });
                    },
                    (error) => {
                        Livewire.dispatch('location-error', {
                            data: {
                                error: 'Location permission denied.'
                            }
                        });
                    }
                );
            }
        });
    });
</script>
</div>