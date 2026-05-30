
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

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Dashboard State
    public $showContactForm = false;
    public $alerts = [];
    
    // Trigger Form State
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

    public function getLocation()
    {
        $this->dispatch('request-geolocation');
    }

    public function handleLocationReceived($data)
    {
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
            if (!$this->latitude || !$this->longitude) {
                throw new \Exception('Lokasi diperlukan. Silakan aktifkan akses lokasi Anda.');
            }

            $request = new EmergencyRequestDTO(
                userId: auth()->id(),
                latitude: $this->latitude,
                longitude: $this->longitude,
                emergencyType: $this->emergencyType,
                notes: $this->notes,
            );

            $emergencyService = app(EmergencyResponseService::class);
            $this->response = $emergencyService->processEmergencyTrigger($request);

            $this->reset(['emergencyType', 'notes']);
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->processing = false;
        }
    }

    public function resolveEmergency(int $logId)
    {
        $log = EmergencyLog::where('user_id', auth()->id())->findOrFail($logId);
        $log->markAsResolved();
    }

    public function openContactModal()
    {
        $this->reset(['contact_name', 'contact_phone', 'contact_relationship', 'contact_email', 'contact_priority']);
        $this->showContactForm = true;
    }

    public function addContact()
    {
        $this->validate([
            'contact_name' => 'required|string|max:255',
            'contact_phone' => 'required|string|max:20',
            'contact_priority' => 'required|integer|between:1,3',
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

        $this->showContactForm = false;
    }

    public function deleteContact($id)
    {
        EmergencyContact::where('user_id', auth()->id())->find($id)?->delete();
    }

    protected function getListeners()
    {
        return [
            'echo:emergency-channel,emergency.triggered' => 'handleIncomingEmergency',
            'location-received' => 'handleLocationReceived',
            'location-error' => 'handleLocationError'
        ];
    }

    public function handleIncomingEmergency($event)
    {
        array_unshift($this->alerts, [
            'type' => $event['type'] ?? $event['emergency_type'] ?? 'Unknown',
            'latitude' => $event['latitude'],
            'longitude' => $event['longitude'],
            'notes' => $event['notes'] ?? ''
        ]);

        if (count($this->alerts) > 5) {
            array_pop($this->alerts);
        }
    }

    public function closeAlert($index)
    {
        unset($this->alerts[$index]);
        $this->alerts = array_values($this->alerts);
    }
}; 
?>
<div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 bg-slate-50 min-h-screen" x-data="{ openTrigger: false }">
    
    {{-- Leaflet Maps Assets diletakkan dengan aman menggunakan @assets bawaan Livewire --}}
    @assets
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endassets

    {{-- REAL-TIME TOAST ALERTS --}}
    <div class="fixed top-5 right-5 z-[100] flex flex-col gap-3 w-full max-w-sm">
        @foreach($alerts as $index => $alert)
        <div class="bg-red-600 border border-red-500 text-white p-4 rounded-xl shadow-2xl flex justify-between items-start transition-all transform duration-300 animate-pulse">
            <div class="flex-1">
                <div class="flex items-center gap-2 font-bold text-sm mb-1">
                    <span>🚨</span> <span>DARURAT MASUK!</span>
                </div>
                <p class="text-xs font-semibold opacity-90">Tipe: {{ ucfirst($alert['type']) }}</p>
                <p class="text-[11px] opacity-75 mt-1">Koordinat: {{ $alert['latitude'] }}, {{ $alert['longitude'] }}</p>
            </div>
            <button wire:click="closeAlert({{ $index }})" class="text-white hover:text-red-200 font-bold text-lg leading-none">&times;</button>
        </div>
        @endforeach
    </div>

    {{-- HEADER SECTION --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Sistem Panel Darurat</h1>
            <p class="text-slate-500 text-sm mt-1">Pantau riwayat, lokasi kejadian aktif, dan kelola kontak darurat Anda.</p>
        </div>
        <button
            @click="openTrigger = true"
            class="w-full sm:w-auto bg-gradient-to-r from-red-600 to-rose-600 text-white px-6 py-3.5 rounded-xl font-bold shadow-md hover:shadow-lg hover:from-red-700 hover:to-rose-700 transition duration-200 flex items-center justify-center gap-2 group">
            <span class="text-lg group-hover:scale-110 transition duration-200">🚨</span> KIRIM SINYAL DARURAT
        </button>
    </div>

    {{-- STATS CARDS GRID --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border-l-4 border-blue-500 p-5 rounded-xl shadow-sm flex justify-between items-center">
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Laporan</h3>
                <p class="text-3xl font-black text-slate-800 mt-1">{{ $stats['total_emergencies'] }}</p>
            </div>
            <div class="p-3 bg-blue-50 rounded-lg text-blue-500 text-xl font-bold">📊</div>
        </div>
        <div class="bg-white border-l-4 border-amber-500 p-5 rounded-xl shadow-sm flex justify-between items-center">
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Kasus Aktif</h3>
                <div class="flex items-center gap-2 mt-1">
                    <p class="text-3xl font-black text-slate-800">{{ $stats['active_emergencies'] }}</p>
                    @if($stats['active_emergencies'] > 0)
                        <span class="flex h-3 w-3 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                        </span>
                    @endif
                </div>
            </div>
            <div class="p-3 bg-amber-50 rounded-lg text-amber-500 text-xl font-bold">⏳</div>
        </div>
        <div class="bg-white border-l-4 border-emerald-500 p-5 rounded-xl shadow-sm flex justify-between items-center">
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Selesai Ditangani</h3>
                <p class="text-3xl font-black text-slate-800 mt-1">{{ $stats['resolved_emergencies'] }}</p>
            </div>
            <div class="p-3 bg-emerald-50 rounded-lg text-emerald-500 text-xl font-bold">✅</div>
        </div>
    </div>

    {{-- MAIN TWO-COLUMN DASHBOARD LAYOUT --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- LEFT COLUMN: MAP & LOGS LIST --}}
        <div class="lg:col-span-2 space-y-6">
            
            {{-- MAP DISPLAY CARD --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                <div class="flex justify-between items-center mb-3 px-2">
                    <h2 class="font-bold text-slate-800 flex items-center gap-2">
                        🗺️ Pemetaan Lokasi Kejadian <span class="text-xs font-normal text-slate-400">(Halaman ini)</span>
                    </h2>
                </div>
                <div wire:ignore id="history-map" class="w-full h-[400px] rounded-xl border border-slate-200 z-10 relative shadow-inner"></div>
            </div>

            {{-- EMERGENCY LOGS CARD --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">📜 Riwayat Laporan</h2>

                <div class="space-y-4">
                    @forelse($logs as $log)
                    <div class="border rounded-xl p-4 transition duration-150 hover:bg-slate-50 {{ $log->status === 'triggered' ? 'border-red-200 bg-red-50/40' : 'border-slate-100' }}">
                        <div class="flex flex-col sm:flex-row justify-between items-start gap-4">
                            <div class="flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold text-slate-700">#{{ $log->id }}</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
                                        @if($log->status === 'triggered') bg-red-100 text-red-700 border border-red-200
                                        @elseif($log->status === 'contacted') bg-amber-100 text-amber-700 border border-amber-200
                                        @elseif($log->status === 'resolved') bg-emerald-100 text-emerald-700 border border-emerald-200
                                        @else bg-slate-100 text-slate-700
                                        @endif">
                                        {{ $log->status }}
                                    </span>
                                    @if($log->emergency_type)
                                    <span class="px-2.5 py-0.5 bg-blue-50 text-blue-700 border border-blue-100 rounded-full text-xs font-medium">
                                        {{ ucfirst($log->emergency_type) }}
                                    </span>
                                    @endif
                                </div>

                                <div class="text-xs text-slate-500 space-y-1">
                                    <p>📅 <strong>Waktu:</strong> {{ $log->triggered_at->format('Y-m-d H:i:s') }}</p>
                                    <p>📍 <strong>Koordinat:</strong> {{ number_format($log->latitude, 6) }}, {{ number_format($log->longitude, 6) }}</p>
                                </div>

                                @if($log->notes)
                                <p class="text-sm text-slate-600 bg-white p-2.5 rounded-lg border border-slate-100 shadow-sm">
                                    💬 {{ $log->notes }}
                                </p>
                                @endif

                                @if($log->response_data)
                                <div class="mt-2 text-[11px] text-slate-400 bg-slate-100/70 p-2 rounded-lg">
                                    🤖 <strong>Keputusan Agen AI:</strong> 
                                    Tingkat: <span class="font-semibold">{{ $log->response_data['severity_determined'] ?? 'N/A' }}</span>,
                                    Fasilitas Ditemukan: <span class="font-semibold">{{ $log->response_data['facilities_found'] ?? 0 }}</span>
                                </div>
                                @endif
                            </div>

                            <div class="flex sm:flex-col gap-2 w-full sm:w-auto">
                                @if($log->latitude && $log->longitude)
                                <button
                                    onclick="window.focusEmergency({{ $log->latitude }}, {{ $log->longitude }}, {{ $log->id }})"
                                    class="flex-1 sm:w-32 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold py-2 px-3 rounded-lg text-center shadow-sm transition">
                                    📍 Lihat di Peta
                                </button>
                                @endif

                                @if($log->status !== 'resolved' && $log->status !== 'cancelled')
                                <button
                                    wire:click="resolveEmergency({{ $log->id }})"
                                    class="flex-1 sm:w-32 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2 px-3 rounded-lg text-center shadow-sm transition">
                                    ✓ Selesaikan
                                </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-slate-400 py-12 border border-dashed rounded-xl">
                        <p class="text-sm">Belum ada riwayat log darurat.</p>
                    </div>
                    @endforelse
                </div>

                <div class="mt-6 shadow-sm">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>

        {{-- RIGHT COLUMN: EMERGENCY CONTACTS --}}
        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">👥 Kontak Darurat</h2>
                    <button wire:click="openContactModal" class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg font-semibold shadow-sm transition">
                        + Tambah
                    </button>
                </div>

                <div class="divide-y divide-slate-100 max-h-[500px] overflow-y-auto pr-1">
                    @forelse($contacts as $contact)
                    <div class="py-3.5 flex justify-between items-center group">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-800 text-sm">{{ $contact->name }}</span>
                                <span class="px-2 py-0.5 text-[9px] bg-slate-100 text-slate-600 font-extrabold rounded border border-slate-200">
                                    Prioritas {{ $contact->priority }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 font-medium">📞 {{ $contact->phone }}</p>
                            @if($contact->relationship)
                            <p class="text-[11px] text-slate-400">Hubungan: {{ $contact->relationship }}</p>
                            @endif
                        </div>
                        <button 
                            wire:click="deleteContact({{ $contact->id }})" 
                            wire:confirm="Apakah Anda yakin ingin menghapus kontak darurat ini?"
                            class="text-slate-300 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition duration-150">
                            🗑️
                        </button>
                    </div>
                    @empty
                    <div class="text-center text-slate-400 py-8">
                        <p class="text-xs">Belum ada daftar kontak darurat.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: TRIGGER EMERGENCY ALERT --}}
    <div x-show="openTrigger" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-transition>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="openTrigger = false"></div>

        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 sm:p-8 border border-slate-100">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-black text-red-600 flex items-center gap-2">🚨 Form Sinyal Darurat</h2>
                    <button @click="openTrigger = false" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
                </div>

                @if($error)
                <div class="bg-red-50 border border-red-200 text-red-700 p-3.5 rounded-xl mb-4 text-xs font-semibold flex items-center gap-2">
                    ⚠️ {{ $error }}
                </div>
                @endif

                @if($response)
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-5 text-sm space-y-1">
                    <h3 class="font-bold text-emerald-900 mb-2 flex items-center gap-1.5">✅ Sinyal Berhasil Dikirim!</h3>
                    <p class="text-xs">ID Laporan: <span class="font-mono font-bold">#{{ $response['emergency_id'] }}</span></p>
                    <p class="text-xs">Tingkat Bahaya: <span class="font-bold uppercase text-red-600">{{ $response['severity'] }}</span></p>
                    <p class="text-xs">Kontak Dinotifikasi: <span class="font-bold">{{ $response['contacts_notified'] }} Orang</span></p>
                </div>
                @endif

                <div class="mb-5 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status Lokasi GPS:</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black tracking-wide {{ $locationEnabled ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'bg-red-100 text-red-700 border border-red-200' }}">
                            {{ $locationEnabled ? '✓ AKTIF' : '✗ MATI' }}
                        </span>
                    </div>
                    <button wire:click="getLocation" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 rounded-lg text-xs shadow-sm transition duration-150">
                        🌐 Dapatkan Lokasi GPS Terbaru
                    </button>
                    @if($latitude && $longitude)
                    <p class="text-[11px] text-slate-500 font-mono mt-2 text-center bg-white border border-slate-100 rounded p-1.5 shadow-sm">
                        Lat: {{ number_format($latitude, 6) }}, Lng: {{ number_format($longitude, 6) }}
                    </p>
                    @endif
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">Tipe Keadaan Darurat</label>
                        <select wire:model="emergencyType" class="w-full border border-slate-200 rounded-xl px-3 py-2.5 text-sm shadow-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 outline-none transition">
                            <option value="">-- Pilih Jenis Kejadian --</option>
                            <option value="medical">Keadaan Medis Kritis</option>
                            <option value="accident">Kecelakaan Lalu Lintas</option>
                            <option value="cardiac">Serangan Jantung</option>
                            <option value="breathing">Gangguan Pernapasan</option>
                            <option value="injury">Luka Parah / Pendarahan</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5 uppercase">Catatan / Detail Tambahan</label>
                        <textarea wire:model="notes" placeholder="Berikan informasi singkat kondisi korban atau lokasi spesifik..." class="w-full border border-slate-200 rounded-xl p-3 text-sm shadow-sm focus:ring-2 focus:ring-red-500/20 focus:border-red-500 outline-none transition" rows="3"></textarea>
                    </div>

                    <div class="pt-2">
                        <div wire:loading wire:target="triggerEmergency" class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden mb-3">
                            <div class="h-full bg-red-600 animate-infinite w-full rounded-full"></div>
                        </div>

                        <button
                            wire:click="triggerEmergency"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-black py-4 px-6 rounded-xl shadow-lg disabled:opacity-40 disabled:cursor-not-allowed transition duration-150"
                            wire:loading.attr="disabled"
                            @disabled(!$locationEnabled || $processing)>
                            <div class="flex items-center justify-center gap-2">
                                <svg wire:loading wire:target="triggerEmergency" class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ $processing ? 'MEMPROSES RESPON...' : '🚨 KIRIM RESPON DARURAT SEKARANG' }}</span>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: ADD EMERGENCY CONTACT --}}
    @if($showContactForm)
    <div class="fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showContactForm', false)"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 border border-slate-100">
                <div class="flex justify-between items-center mb-5">
                    <h2 class="text-lg font-bold text-slate-800">➕ Tambah Kontak Darurat</h2>
                    <button wire:click="$set('showContactForm', false)" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap</label>
                        <input type="text" wire:model="contact_name" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">No. Telepon / WhatsApp</label>
                        <input type="text" wire:model="contact_phone" placeholder="Contoh: 08123456789" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Hubungan / Relasi</label>
                            <input type="text" wire:model="contact_relationship" placeholder="Misal: Ibu, Ayah, Teman" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Prioritas Hubungi</label>
                            <select wire:model="contact_priority" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                                <option value="1">1 (Utama)</option>
                                <option value="2">2 (Menengah)</option>
                                <option value="3">3 (Cadangan)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email (Opsional)</label>
                        <input type="email" wire:model="contact_email" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition">
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button wire:click="$set('showContactForm', false)" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold py-2.5 rounded-xl text-sm transition">Batal</button>
                        <button wire:click="addContact" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl text-sm shadow-sm transition">Simpan Kontak</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- DATA BRIDGE UNTUK REAL-TIME LIVEWIRE MAP DATA UPDATE --}}
    @php
        $bridgeData = collect($logs->items())->map(fn($log) => [
            'id' => $log->id,
            'latitude' => $log->latitude,
            'longitude' => $log->longitude,
            'status' => $log->status,
            'emergency_type' => $log->emergency_type,
            'notes' => $log->notes ?? '',
            'triggered_at' => $log->triggered_at ? $log->triggered_at->format('Y-m-d H:i:s') : ''
        ])->toJson();
    @endphp
    <div id="map-data-bridge" data-markers="{{ $bridgeData }}" class="hidden"></div>

    {{-- SCRIPTS AREA --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            
            Livewire.on('request-geolocation', () => {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            Livewire.dispatch('location-received', {
                                data: { latitude: position.coords.latitude, longitude: position.coords.longitude }
                            });
                        },
                        (error) => {
                            Livewire.dispatch('location-error', {
                                data: { error: 'Izin akses lokasi ditolak oleh perangkat.' }
                            });
                        }
                    );
                }
            });

            const mapContainer = document.getElementById('history-map');
            if (mapContainer) {
                window.emergencyMap = L.map('history-map').setView([-6.3927, 106.8286], 11);
                window.emergencyMarkers = {};

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(window.emergencyMap);

                window.updateMapMarkers = function() {
                    const bridge = document.getElementById('map-data-bridge');
                    if (!bridge) return;

                    for (let id in window.emergencyMarkers) {
                        window.emergencyMap.removeLayer(window.emergencyMarkers[id]);
                    }
                    window.emergencyMarkers = {};

                    const logs = JSON.parse(bridge.getAttribute('data-markers') || '[]');
                    const bounds = [];

                    logs.forEach(log => {
                        if (log.latitude && log.longitude) {
                            let iconColor = log.status === 'triggered' ? '#ef4444' : (log.status === 'contacted' ? '#f59e0b' : '#10b981');
                            let statusEmoji = log.status === 'resolved' ? '✅' : '🚨';

                            let popupContent = `
                                <div class="text-xs p-1 font-sans" style="min-width: 160px;">
                                    <strong class="text-sm text-slate-800">${statusEmoji} #${log.id} - ${log.emergency_type.toUpperCase()}</strong>
                                    <p class="text-slate-400 text-[10px] my-1">${log.triggered_at}</p>
                                    <hr class="my-1.5 border-slate-100">
                                    <p class="text-slate-600"><strong>Status:</strong> <span class="uppercase font-bold" style="color:${iconColor}">${log.status}</span></p>
                                    <p class="text-slate-600 mt-1"><strong>Catatan:</strong> ${log.notes || '-'}</p>
                                </div>
                            `;

                            let marker = L.circleMarker([log.latitude, log.longitude], {
                                radius: 9,
                                fillColor: iconColor,
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.85
                            }).addTo(window.emergencyMap).bindPopup(popupContent);

                            window.emergencyMarkers[log.id] = marker;
                            bounds.push([log.latitude, log.longitude]);
                        }
                    });

                    if (bounds.length > 0) {
                        window.emergencyMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
                    }
                };

                window.updateMapMarkers();

                Livewire.hook('morph.updated', ({ el }) => {
                    if (el.id === 'map-data-bridge') {
                        window.updateMapMarkers();
                    }
                });
            }

            window.focusEmergency = function(lat, lng, id) {
                if (window.emergencyMap) {
                    window.emergencyMap.setView([lat, lng], 16, { animate: true, duration: 1 });
                    if (window.emergencyMarkers[id]) {
                        window.emergencyMarkers[id].openPopup();
                    }
                    document.getElementById('history-map').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            };
        });
    </script>
</div>
