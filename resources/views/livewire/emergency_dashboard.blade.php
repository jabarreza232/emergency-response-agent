<?php

use Livewire\Volt\Component;
use App\Models\EmergencyLog;
use App\Models\UserNotification; 
use App\Models\Hospital;
use App\Models\PoliceStation;
use App\Models\FireStation;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, on};

state([]);

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public $alerts = [];
    public $latestLogId = null; // Menyimpan ID laporan terakhir

    public function mount()
    {
        // Set ID laporan terakhir saat pertama kali halaman dimuat
        $this->latestLogId = EmergencyLog::max('id');
    }

    public function with(): array
    {
        // PENDETEKSI LAPORAN BARU (Realtime via Polling)
        $currentMaxId = EmergencyLog::max('id');
        
        if ($this->latestLogId !== null && $currentMaxId > $this->latestLogId) {
            // Jika ada ID baru yang lebih besar, bunyikan alarm!
            $this->dispatch('trigger-alarm');
            $this->latestLogId = $currentMaxId; // Update ID terakhir
        } elseif ($this->latestLogId === null) {
            $this->latestLogId = $currentMaxId;
        }

        return [
            'logs' => EmergencyLog::with('user')
                ->orderBy('triggered_at', 'desc')
                ->paginate(10),
            
            'stats' => [
                'total_emergencies' => EmergencyLog::count(),
                'active_emergencies' => EmergencyLog::whereNotIn('status', ['resolved', 'cancelled'])->count(),
                'resolved_emergencies' => EmergencyLog::where('status', 'resolved')->count(),
            ],

            // Mengambil semua data fasilitas untuk dikirim ke peta JavaScript
            'hospitals' => Hospital::where('is_active', true)->get(['id', 'name', 'latitude', 'longitude', 'phone']),
            'policeStations' => PoliceStation::where('is_active', true)->get(['id', 'name', 'latitude', 'longitude', 'phone']),
            'fireStations' => FireStation::where('is_active', true)->get(['id', 'name', 'latitude', 'longitude', 'phone']),
        ];
    }

    public function resolveEmergency(int $logId)
    {
        $log = EmergencyLog::findOrFail($logId);
        
        if(method_exists($log, 'markAsResolved')) {
            $log->markAsResolved();
        } else {
            $log->status = 'resolved';
            $log->resolved_at = now();
            $log->save();
        }

        UserNotification::create([
            'user_id' => $log->user_id,
            'emergency_log_id' => $log->id,
            'title' => 'Laporan Selesai Ditangani',
            'message' => "Laporan darurat Anda (#{$log->id}) untuk jenis kejadian " . strtoupper($log->emergency_type ?? 'Lainnya') . " telah diselesaikan oleh agen kami. Terima kasih atas laporan Anda.",
            'is_read' => false,
        ]);

        session()->flash('status', "Kasus #{$log->id} berhasil diselesaikan. Notifikasi telah dikirim ke pelapor.");
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect('/', navigate: true);
    }

    protected function getListeners()
    {
        return [
            'echo:emergency-channel,emergency.triggered' => 'handleIncomingEmergency',
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

        // Memicu event browser untuk membunyikan alarm di Frontend (Jika pakai WebSocket/Echo)
        $this->dispatch('trigger-alarm');
    }

    public function closeAlert($index)
    {
        unset($this->alerts[$index]);
        $this->alerts = array_values($this->alerts);
    }
}; 
?>

<div class="min-h-screen bg-slate-900 pb-12" wire:poll.5s>
    @assets
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endassets

    {{-- File Audio Sirine Lokal --}}
    <audio id="emergency-siren" loop preload="auto">
        <source src="{{ asset('audio/sirine.mp3') }}" type="audio/mpeg">
    </audio>

    {{-- State Alpine untuk Sistem Alarm dengan Audio Unlocker Mutlak --}}
    <div class="min-h-screen bg-slate-900 pb-12" 
         x-data="{
            isAlarmRinging: false,
            alarmTimeout: null,
            audioUnlocked: false,
            
            unlockAudio() {
                if (this.audioUnlocked) return;
                
                this.audioUnlocked = true; 
                
                let siren = document.getElementById('emergency-siren');
                if (siren) {
                    siren.muted = true;
                    siren.play().then(() => {
                        siren.pause();
                        siren.currentTime = 0;
                        siren.muted = false; 
                    }).catch(e => {
                        console.log('Menunggu klik pengguna...');
                    });
                }
            },

            playAlarm() {
                let siren = document.getElementById('emergency-siren');
                if (siren) {
                    siren.muted = false;
                    siren.play().then(() => {
                        this.audioUnlocked = true;
                    }).catch(e => {
                        console.warn('Autoplay dicegah browser! Harap klik halaman.');
                        this.audioUnlocked = false; 
                    });
                    
                    this.isAlarmRinging = true;
                    
                    clearTimeout(this.alarmTimeout);
                    this.alarmTimeout = setTimeout(() => {
                        this.stopAlarm();
                    }, 300000);
                }
            },
            
            stopAlarm() {
                let siren = document.getElementById('emergency-siren');
                if (siren) {
                    siren.pause();
                    siren.currentTime = 0;
                }
                this.isAlarmRinging = false;
                clearTimeout(this.alarmTimeout);
            }
         }" 
         @click.window="unlockAudio()"
         @trigger-alarm.window="playAlarm()">

        {{-- Banner Peringatan Izin Audio --}}
        <div x-show="!audioUnlocked" x-transition class="bg-blue-600 border-b border-blue-500 text-white text-center py-2 px-4 text-xs font-bold cursor-pointer hover:bg-blue-700 transition flex items-center justify-center gap-2 z-[999] relative">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 animate-pulse"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
            Sistem membutuhkan izin suara. Klik di mana saja pada halaman ini untuk mengaktifkan sirine otomatis.
        </div>

        {{-- Tombol Matikan Alarm Mengambang --}}
        <div x-show="isAlarmRinging" x-transition.opacity style="display: none;" class="fixed top-6 left-1/2 transform -translate-x-1/2 z-[600]">
            <button @click="stopAlarm()" class="bg-red-600 animate-pulse hover:bg-red-700 text-white px-6 py-2.5 rounded-full font-bold shadow-[0_0_20px_rgba(239,68,68,0.6)] flex items-center gap-2 border-2 border-red-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M13.5 4.06c0-1.336-1.616-2.005-2.56-1.06l-4.5 4.5H4.508c-1.141 0-2.318.664-2.66 1.905A9.76 9.76 0 0 0 1.5 12c0 .898.121 1.768.35 2.595.341 1.24 1.518 1.905 2.659 1.905h1.93l4.5 4.5c.945.945 2.561.276 2.561-1.06V4.06ZM18.584 5.106a.75.75 0 0 1 1.06 0c3.808 3.807 3.808 9.98 0 13.788a.75.75 0 0 1-1.06-1.06 8.25 8.25 0 0 0 0-11.668.75.75 0 0 1 0-1.06Z" /><path d="M15.932 7.757a.75.75 0 0 1 1.061 0 6 6 0 0 1 0 8.486.75.75 0 0 1-1.06-1.061 4.5 4.5 0 0 0 0-6.364.75.75 0 0 1 0-1.06Z" /></svg>
                Matikan Sirine
            </button>
        </div>

        {{-- REAL-TIME TOAST ALERTS --}}
        <div class="fixed top-20 right-5 z-[100] flex flex-col gap-3 w-full max-w-sm">
            @foreach($alerts as $index => $alert)
            <div class="bg-red-600 border border-red-500 text-white p-4 rounded-xl shadow-2xl flex justify-between items-start transition-all transform duration-300 animate-pulse">
                <div class="flex-1">
                    <div class="flex items-center gap-2 font-bold text-sm mb-1">
                        <span>🚨</span> <span>DARURAT MASUK!</span>
                    </div>
                    <p class="text-xs font-semibold opacity-90">Tipe: {{ ucfirst($alert['type']) }}</p>
                    <p class="text-[11px] opacity-75 mt-1">Koordinat: {{ $alert['latitude'] }}, {{ $alert['longitude'] }}</p>
                </div>
                <button wire:click="closeAlert({{ $index }})" @click="stopAlarm()" class="text-white hover:text-red-200 font-bold text-lg leading-none">&times;</button>
            </div>
            @endforeach
        </div>

        {{-- TOPBAR --}}
        <div class="bg-slate-950/90 backdrop-blur-md px-4 py-4 sm:px-8 shadow-md flex justify-between items-center border-b border-slate-800 mb-8 sticky top-0 z-50">
            <div class="flex items-center gap-3">
                <div class="p-1.5 rounded-lg bg-red-600 text-white shadow-lg shadow-red-600/30">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" clip-rule="evenodd" />
                    </svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">ERA <span class="text-slate-400 font-medium">Log Panel</span></span>
            </div>
            
            <div class="flex items-center gap-4">
                {{-- MENU MASTER DATA --}}
                <div class="relative" x-data="{ openMaster: false }">
                    <button @click="openMaster = !openMaster" @click.outside="openMaster = false" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-800 transition text-slate-300 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" /></svg>
                        <span class="text-sm font-semibold hidden sm:block">Master Data</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <div x-show="openMaster" x-transition.opacity style="display: none;" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden z-[600] py-2">
                        <div class="px-4 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Fasilitas</div>
                        <a href="{{ route('admin.facilities') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 hover:text-green-600 transition">🏥 Rumah Sakit</a>
                        <a href="{{ route('admin.facilities') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 hover:text-blue-600 transition">🚓 Kepolisian</a>
                        <a href="{{ route('admin.facilities') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 hover:text-orange-600 transition">🚒 Pemadam Kebakaran</a>
                        
                        <div class="border-t border-slate-100 my-1"></div>
                        <div class="px-4 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider">Pengguna</div>
                        <a href="{{ route('admin.users') }}" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 hover:text-indigo-600 transition">👥 Data Warga / Reporter</a>
                    </div>
                </div>

                <div class="h-6 w-px bg-slate-700 mx-1"></div>

                {{-- MENU PROFIL --}}
                <div class="relative" x-data="{ openProfile: false }">
                    <button @click="openProfile = !openProfile" @click.outside="openProfile = false" class="flex items-center gap-2 bg-slate-800/50 p-1.5 pr-3 rounded-full hover:bg-slate-700 transition border border-slate-700">
                        <div class="w-7 h-7 bg-red-600 rounded-full flex items-center justify-center text-white font-bold text-xs">
                            {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                        </div>
                        <span class="text-sm font-semibold text-slate-300 hidden sm:block">Admin</span>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>

                    <div x-show="openProfile" x-transition.opacity style="display: none;" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden z-[600]">
                        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
                            <p class="text-sm text-slate-900 font-bold truncate">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</p>
                        </div>
                        <a href="/profile" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg> Profil Sistem
                        </a>
                        
                        <button wire:click="logout" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2 border-t border-slate-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg> Keluar Penuh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Pesan Status Penyelesaian --}}
            @if (session('status'))
                <div class="mb-6 bg-emerald-500 border border-emerald-400 p-4 rounded-xl shadow-lg flex justify-between items-start animate-bounce text-white">
                    <div class="flex gap-3">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <div>
                            <h3 class="font-bold text-sm">Aksi Berhasil!</h3>
                            <p class="text-xs text-emerald-100 mt-0.5">{{ session('status') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- STATS CARDS --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <div class="bg-slate-800 border-l-4 border-blue-500 p-5 rounded-2xl shadow-lg flex justify-between items-center">
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Laporan</h3>
                        <p class="text-3xl font-black text-white mt-1">{{ $stats['total_emergencies'] }}</p>
                    </div>
                    <div class="p-3 bg-slate-700/50 rounded-xl text-blue-400 text-xl font-bold">📊</div>
                </div>
                <div class="bg-slate-800 border-l-4 border-amber-500 p-5 rounded-2xl shadow-lg flex justify-between items-center">
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Kasus Aktif (Seluruh Sistem)</h3>
                        <div class="flex items-center gap-2 mt-1">
                            <p class="text-3xl font-black text-white">{{ $stats['active_emergencies'] }}</p>
                            @if($stats['active_emergencies'] > 0)
                                <span class="flex h-3 w-3 relative">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="p-3 bg-slate-700/50 rounded-xl text-amber-400 text-xl font-bold">⏳</div>
                </div>
                <div class="bg-slate-800 border-l-4 border-emerald-500 p-5 rounded-2xl shadow-lg flex justify-between items-center">
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Selesai Ditangani</h3>
                        <p class="text-3xl font-black text-white mt-1">{{ $stats['resolved_emergencies'] }}</p>
                    </div>
                    <div class="p-3 bg-slate-700/50 rounded-xl text-emerald-400 text-xl font-bold">✅</div>
                </div>
            </div>

            <div class="space-y-6">
                {{-- MAP DISPLAY --}}
                <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 p-4 overflow-hidden">
                    <div class="flex justify-between items-center mb-3 px-2">
                        <h2 class="font-bold text-white flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-red-500"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
                            Pemetaan Historis Kejadian Seluruh Wilayah
                        </h2>
                    </div>
                    <div wire:ignore id="history-map" class="w-full h-[350px] sm:h-[450px] rounded-2xl border border-slate-900 z-10 relative shadow-inner"></div>
                </div>

                {{-- EMERGENCY LOGS CARD --}}
                <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 p-6 sm:p-8">
                    <h2 class="text-xl font-extrabold text-white mb-6 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                        Tabel Log Laporan Warga
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @forelse($logs as $log)
                        <div class="border rounded-2xl p-5 transition duration-150 {{ $log->status === 'triggered' ? 'border-red-500/50 bg-red-950/20' : 'border-slate-700 bg-slate-900/50 hover:bg-slate-700/50' }}">
                            <div class="flex flex-col h-full justify-between gap-4">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-300">#{{ $log->id }}</span>
                                            <span class="text-xs font-semibold text-slate-400">👤 {{ $log->user->name ?? 'User '.$log->user_id }}</span>
                                        </div>
                                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest
                                            @if($log->status === 'triggered') bg-red-500/20 text-red-400 border border-red-500/30
                                            @elseif($log->status === 'contacted') bg-amber-500/20 text-amber-400 border border-amber-500/30
                                            @elseif($log->status === 'resolved') bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
                                            @else bg-slate-700 text-slate-300
                                            @endif">
                                            {{ $log->status }}
                                        </span>
                                    </div>

                                    <div>
                                        <div class="inline-block px-2.5 py-1 mb-2 bg-slate-800 text-blue-400 border border-slate-700 rounded-lg text-xs font-bold uppercase">
                                            {{ $log->emergency_type ?? 'Lainnya' }}
                                        </div>
                                        <div class="text-xs text-slate-400 space-y-1.5 font-medium">
                                            <p class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg> {{ $log->triggered_at->format('d M Y, H:i') }}</p>
                                            <p class="flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg> {{ number_format($log->latitude, 5) }}, {{ number_format($log->longitude, 5) }}</p>
                                        </div>
                                    </div>

                                    @if($log->notes)
                                    <p class="text-sm text-slate-300 bg-slate-950/50 p-3 rounded-xl border border-slate-800">
                                        "{{ $log->notes }}"
                                    </p>
                                    @endif
                                </div>

                                <div class="flex gap-2 w-full mt-2 pt-4 border-t border-slate-700/50">
                                    @if($log->latitude && $log->longitude)
                                    <button onclick="window.focusEmergency({{ $log->latitude }}, {{ $log->longitude }}, {{ $log->id }}, '{{ $log->emergency_type }}')" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white text-xs font-bold py-2.5 px-3 rounded-xl transition flex justify-center items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.565 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg> Fokus Peta
                                    </button>
                                    @endif

                                    @if($log->status !== 'resolved' && $log->status !== 'cancelled')
                                    <button wire:click="resolveEmergency({{ $log->id }})" 
                                            wire:confirm="Yakin ingin menandai laporan ini sebagai Selesai?"
                                            wire:loading.attr="disabled"
                                            class="flex-1 bg-emerald-600/20 hover:bg-emerald-600 text-emerald-400 hover:text-white border border-emerald-500/50 text-xs font-bold py-2.5 px-3 rounded-xl transition flex justify-center items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                        
                                        <span wire:loading.remove wire:target="resolveEmergency({{ $log->id }})" class="flex items-center gap-1.5">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg> 
                                            Selesaikan
                                        </span>
                                        
                                        <span wire:loading wire:target="resolveEmergency({{ $log->id }})" class="flex items-center gap-1.5">
                                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            Memproses...
                                        </span>
                                        
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="col-span-full text-center text-slate-500 py-16 border border-slate-700 border-dashed rounded-3xl bg-slate-900/30">
                            <p class="text-sm font-medium">Belum ada riwayat log darurat masuk.</p>
                        </div>
                        @endforelse
                    </div>
                    <div class="mt-8 text-white">{{ $logs->links() }}</div>
                </div>
            </div>
        </div>

        {{-- DATA BRIDGE UNTUK MAP --}}
        @php
            $bridgeData = collect($logs->items())->map(fn($log) => [
                'id' => $log->id,
                'latitude' => $log->latitude,
                'longitude' => $log->longitude,
                'status' => $log->status,
                'emergency_type' => $log->emergency_type,
                'notes' => $log->notes ?? '',
                'triggered_at' => $log->triggered_at ? $log->triggered_at->format('Y-m-d H:i:s') : '',
                'user_name' => $log->user->name ?? 'Unknown'
            ])->toJson();
        @endphp
        <div id="map-data-bridge" data-markers="{{ $bridgeData }}" class="hidden"></div>

        <script>
            // Data Fasilitas dari PHP ke JS
            window.facilityData = {
                'hospital': @json($hospitals),
                'police': @json($policeStations),
                'fire': @json($fireStations)
            };

            // Definisi Ikon Fasilitas
            var hospitalIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background-color:#10b981; width:22px; height:22px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; box-shadow: 0 0 10px rgba(16,185,129,0.5);'><b style='color:white; font-size:12px;'>H</b></div>", iconSize: [22, 22], iconAnchor: [11, 11] });
            var policeIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background-color:#3b82f6; width:22px; height:22px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; box-shadow: 0 0 10px rgba(59,130,246,0.5);'><b style='color:white; font-size:12px;'>P</b></div>", iconSize: [22, 22], iconAnchor: [11, 11] });
            var fireIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background-color:#f97316; width:22px; height:22px; border-radius:50%; border:2px solid white; display:flex; align-items:center; justify-content:center; box-shadow: 0 0 10px rgba(249,115,22,0.5);'><b style='color:white; font-size:12px;'>F</b></div>", iconSize: [22, 22], iconAnchor: [11, 11] });

            document.addEventListener('livewire:initialized', () => {
                const mapContainer = document.getElementById('history-map');
                if (mapContainer) {
                    window.emergencyMap = L.map('history-map').setView([-6.3927, 106.8286], 11);
                    window.emergencyMarkers = {};
                    
                    // Group Layer untuk Fasilitas agar mudah dihapus/dibersihkan
                    window.facilityLayerGroup = L.layerGroup().addTo(window.emergencyMap);

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(window.emergencyMap);

                    window.updateMapMarkers = function() {
                        const bridge = document.getElementById('map-data-bridge');
                        if (!bridge) return;

                        for (let id in window.emergencyMarkers) { window.emergencyMap.removeLayer(window.emergencyMarkers[id]); }
                        window.emergencyMarkers = {};

                        const logs = JSON.parse(bridge.getAttribute('data-markers') || '[]');
                        const bounds = [];

                        logs.forEach(log => {
                            if (log.latitude && log.longitude) {
                                let iconColor = log.status === 'triggered' ? '#ef4444' : (log.status === 'contacted' ? '#f59e0b' : '#10b981');
                                let pulseClass = log.status === 'triggered' ? 'animation: pulse-red 2s infinite;' : '';

                                let customIcon = L.divIcon({
                                    className: 'custom-div-icon',
                                    html: `<div style='background-color:${iconColor}; width:16px; height:16px; border-radius:50%; border:2px solid #1e293b; box-shadow: 0 0 10px ${iconColor}; ${pulseClass}'></div>`,
                                    iconSize: [16, 16], iconAnchor: [8, 8]
                                });

                                let popupContent = `
                                    <div class="text-xs p-1 font-sans" style="min-width: 160px;">
                                        <strong class="text-sm text-slate-800">#${log.id} - ${log.emergency_type.toUpperCase()}</strong>
                                        <p class="text-slate-400 text-[10px] my-1">${log.triggered_at} | 👤 ${log.user_name}</p>
                                        <hr class="my-1.5 border-slate-100">
                                        <p class="text-slate-600"><strong>Status:</strong> <span class="uppercase font-bold" style="color:${iconColor}">${log.status}</span></p>
                                    </div>
                                `;

                                let marker = L.marker([log.latitude, log.longitude], {icon: customIcon, zIndexOffset: 1000}).addTo(window.emergencyMap).bindPopup(popupContent);
                                window.emergencyMarkers[log.id] = marker;
                                bounds.push([log.latitude, log.longitude]);
                            }
                        });

                        if (bounds.length > 0) { window.emergencyMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 }); }
                    };

                    window.updateMapMarkers();
                    Livewire.hook('morph.updated', ({ el }) => { if (el.id === 'map-data-bridge') { window.updateMapMarkers(); } });
                }

                // FUNGSI UPDATE MENDETEKSI TIPE DARURAT DAN RADIUS 5KM
                window.focusEmergency = function(lat, lng, id, type) {
                    if (window.emergencyMap) {
                        
                        // 1. Fokus Kamera Peta ke Laporan
                        const emergencyLatLng = L.latLng(lat, lng);
                        window.emergencyMap.setView(emergencyLatLng, 14, { animate: true, duration: 1.5 });
                        
                        if (window.emergencyMarkers[id]) { 
                            window.emergencyMarkers[id].openPopup(); 
                        }

                        // 2. Bersihkan layer sebelumnya (marker fasilitas & lingkaran)
                        window.facilityLayerGroup.clearLayers();

                        // 3. Tentukan kategori fasilitas yang relevan
                        let facTypeToShow = null;
                        let facIcon = null;
                        let facTitle = '';
                        
                        const typeLower = (type || '').toLowerCase();
                        
                        if (['kebakaran'].includes(typeLower)) {
                            facTypeToShow = 'fire';
                            facIcon = fireIcon;
                            facTitle = 'Pemadam Kebakaran';
                        } else if (['medis', 'cardiac', 'breathing', 'injury'].includes(typeLower)) {
                            facTypeToShow = 'hospital';
                            facIcon = hospitalIcon;
                            facTitle = 'Fasilitas Medis';
                        } else if (['kriminal', 'accident'].includes(typeLower)) {
                            facTypeToShow = 'police';
                            facIcon = policeIcon;
                            facTitle = 'Kantor Polisi';
                        }

                        // 4. Proses Filter Radius 5 KM
                        if (facTypeToShow && window.facilityData[facTypeToShow]) {
                            const radiusInMeters = 5000; // 5 KM
                            
                            // A. Gambar Lingkaran Visual 5km di peta
                            L.circle(emergencyLatLng, {
                                color: '#3b82f6',
                                fillColor: '#3b82f6',
                                fillOpacity: 0.1,
                                radius: radiusInMeters,
                                weight: 1,
                                interactive: false // Agar lingkaran tidak menghalangi klik marker
                            }).addTo(window.facilityLayerGroup);

                            let countFound = 0;

                            // B. Filter dan tambahkan marker yang masuk radius
                            window.facilityData[facTypeToShow].forEach(fac => {
                                const facLatLng = L.latLng(fac.latitude, fac.longitude);
                                const distanceMeters = emergencyLatLng.distanceTo(facLatLng);

                                if (distanceMeters <= radiusInMeters) {
                                    countFound++;
                                    let distanceKm = (distanceMeters / 1000).toFixed(2);
                                    
                                    let popup = `
                                        <div class="text-xs font-sans">
                                            <b class='text-sm text-slate-800'>${fac.name}</b><br>
                                            <span class='text-slate-500 font-bold uppercase text-[10px]'>${facTitle}</span><br>
                                            <span class='text-slate-600 mt-1 block'>📞 ${fac.phone || '-'}</span>
                                            <div class='mt-2 pt-1 border-t border-slate-200 text-blue-600 font-bold'>
                                                📏 Jarak: ${distanceKm} KM
                                            </div>
                                        </div>
                                    `;

                                    L.marker(facLatLng, {icon: facIcon})
                                     .addTo(window.facilityLayerGroup)
                                     .bindPopup(popup);
                                }
                            });
                            
                            if(countFound === 0) {
                                console.log(`Tidak ada fasilitas ${facTitle} yang ditemukan dalam radius 5km.`);
                            }
                        }

                        // Auto scroll ke arah peta
                        document.getElementById('history-map').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                };
            });
        </script>
    </div>
</div>
