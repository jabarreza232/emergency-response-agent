<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\EmergencyLog;
use App\Models\Hospital;
use App\Models\PoliceStation;
use App\Models\FireStation;
use App\Models\UserNotification; 
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app')] class extends Component
{
    public $latitude;
    public $longitude;
    public $emergency_type = '';
    public $notes;
    
    public $gps_status = 'Mencari sinyal GPS...';
    public $is_gps_locked = false;

    public $showEmergencyModal = false;
    
    public $hospitals = [];
    public $policeStations = [];
    public $fireStations = [];
    
    public $emergencyHistory = []; 
    public $userHistory = []; 
    public $userNotifications = [];

    public function mount()
    {
        $this->hospitals = Hospital::where('is_active', true)->get(['id', 'name', 'latitude', 'longitude', 'type', 'phone']);
        $this->policeStations = PoliceStation::where('is_active', true)->get(['id', 'name', 'latitude', 'longitude', 'phone']);
        $this->fireStations = FireStation::where('is_active', true)->get(['id', 'name', 'latitude', 'longitude', 'phone']);

        $this->emergencyHistory = EmergencyLog::get(['id', 'latitude', 'longitude', 'emergency_type', 'status', 'triggered_at']);
        $this->userHistory = EmergencyLog::where('user_id', Auth::id())->orderBy('created_at', 'desc')->get();
        
        $this->loadNotifications();
    }

    public function loadNotifications()
    {
        $this->userNotifications = UserNotification::where('user_id', Auth::id())
                                    ->orderBy('created_at', 'desc')
                                    ->get();
    }

    public function markNotificationsAsRead()
    {
        UserNotification::where('user_id', Auth::id())->where('is_read', false)->update(['is_read' => true]);
        $this->loadNotifications(); 
    }

    public function submitEmergency()
    {
        $this->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'emergency_type' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ], [
            'latitude.required' => 'Sinyal GPS belum terkunci. Tunggu beberapa saat.',
            'emergency_type.required' => 'Pilih jenis darurat!',
        ]);

        $log = EmergencyLog::create([
            'user_id' => Auth::id(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'emergency_type' => $this->emergency_type,
            'notes' => $this->notes,
            'status' => 'triggered',
            'triggered_at' => now(),
        ]);

        $this->userHistory = EmergencyLog::where('user_id', Auth::id())->orderBy('created_at', 'desc')->get();

        $this->showEmergencyModal = false;
        session()->flash('status', 'Laporan Darurat Berhasil Dikirim! Tim kami segera merespons.');
        $this->reset(['notes', 'emergency_type']);

        $this->dispatch('emergency-created', [
            'lat' => $log->latitude, 'lng' => $log->longitude, 'type' => $log->emergency_type, 'status' => $log->status
        ]);
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    @assets
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <style>
            .leaflet-bottom.leaflet-right { margin-bottom: 120px; margin-right: 15px;}
            @keyframes pulse-red {
                0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
                70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
                100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
            }
            .no-scrollbar::-webkit-scrollbar { display: none; }
            .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    @endassets

    <div class="h-screen w-full bg-slate-900 overflow-hidden relative" 
         x-data="{ showSidebar: false, showHistory: false, showNotifications: false, openProfile: false, activeTab: 'hospital' }">
        
        <div id="map" class="absolute inset-0 z-0" wire:ignore></div>

        <div class="absolute top-0 w-full z-[500] bg-slate-950/90 backdrop-blur-md px-4 py-3 sm:px-6 shadow-md flex justify-between items-center border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="p-1.5 rounded-lg bg-red-600 text-white shadow-lg shadow-red-600/30">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" clip-rule="evenodd" /></svg>
                </div>
                <span class="text-xl font-bold text-white tracking-tight">ERA <span class="text-slate-400 font-medium">Radar</span></span>
            </div>
            
            <div class="flex items-center gap-1 sm:gap-3">
                
                <button @click="showHistory = true" class="p-2 text-slate-300 hover:text-white transition rounded-full hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Zm3.75 11.625a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                </button>

                <button @click="showNotifications = true" class="relative p-2 text-slate-300 hover:text-white transition rounded-full hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
                    @if(collect($userNotifications)->where('is_read', false)->count() > 0)
                        <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 border border-slate-900"></span></span>
                    @endif
                </button>

                <button @click="showSidebar = true" class="flex items-center gap-2 p-2 sm:px-4 rounded-full bg-slate-800 text-slate-300 hover:text-white transition shadow-inner">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" /></svg>
                    <span class="hidden sm:inline text-sm font-semibold">Fasilitas Darurat</span>
                </button>

                <div class="relative ml-2 border-l border-slate-700 pl-2">
                    <button @click="openProfile = !openProfile" @click.outside="openProfile = false" class="flex items-center gap-2 bg-slate-800/50 p-1.5 pr-3 rounded-full hover:bg-slate-700 transition border border-slate-700">
                        <div class="w-7 h-7 bg-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-xs">{{ substr(Auth::user()->name ?? 'U', 0, 1) }}</div>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div x-show="openProfile" x-transition.opacity style="display: none;" class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-slate-200 overflow-hidden z-[600]">
                        <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
                            <p class="text-sm text-slate-900 font-bold truncate">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</p>
                        </div>
                        <a href="/profile" wire:navigate class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-100 flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg> Profil Saya</a>
                        <button wire:click="logout" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center gap-2 border-t border-slate-100"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg> Keluar</button>
                    </div>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="absolute top-24 left-1/2 transform -translate-x-1/2 z-[500] w-[90%] max-w-md animate-bounce">
                <div class="bg-green-500 rounded-xl p-3 flex items-center gap-3 shadow-2xl shadow-green-500/40 border border-green-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="white" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="font-bold text-white text-sm">{{ session('status') }}</span>
                </div>
            </div>
        @endif

        <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 z-[400]">
            <div class="absolute inset-0 bg-red-600 rounded-full animate-ping opacity-75"></div>
            <button wire:click="$set('showEmergencyModal', true)" 
                class="relative flex flex-col items-center justify-center w-20 h-20 bg-gradient-to-b from-red-500 to-red-700 rounded-full shadow-[0_10px_25px_rgba(220,38,38,0.6)] border-4 border-slate-900 text-white hover:scale-105 active:scale-95 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-8 h-8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                <span class="text-[10px] font-black uppercase mt-0.5 tracking-wider">SOS</span>
            </button>
        </div>

        <div class="absolute inset-y-0 right-0 z-[450] w-80 sm:w-96 bg-white border-l border-slate-200 shadow-2xl transform transition-transform duration-300 ease-in-out flex flex-col pt-[64px] sm:pt-[72px]" :class="showSidebar ? 'translate-x-0' : 'translate-x-full'">
            
            <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-lg">Direktori Bantuan</h3>
                <button @click="showSidebar = false" class="p-1 text-slate-400 hover:text-slate-600 bg-slate-200 rounded-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="flex border-b border-slate-200 bg-white shadow-sm z-10">
                <button @click="activeTab = 'hospital'" :class="activeTab === 'hospital' ? 'border-green-500 text-green-600 bg-green-50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'" class="flex-1 py-3 text-xs font-bold uppercase tracking-wider border-b-2 transition-all flex justify-center items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg> RS
                </button>
                <button @click="activeTab = 'police'" :class="activeTab === 'police' ? 'border-blue-500 text-blue-600 bg-blue-50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'" class="flex-1 py-3 text-xs font-bold uppercase tracking-wider border-b-2 transition-all flex justify-center items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg> Polisi
                </button>
                <button @click="activeTab = 'fire'" :class="activeTab === 'fire' ? 'border-orange-500 text-orange-600 bg-orange-50' : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'" class="flex-1 py-3 text-xs font-bold uppercase tracking-wider border-b-2 transition-all flex justify-center items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" /></svg> Damkar
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-3 pb-24 bg-slate-50"> 
                
                <div x-show="activeTab === 'hospital'" x-transition.opacity>
                    @forelse($hospitals as $rs)
                        <div class="bg-white p-3 mb-2 rounded-xl border border-slate-200 hover:border-green-300 hover:shadow-md transition cursor-pointer group" @click="showFacilityOnMap({{ $rs->id }}, 'hospital'); if(window.innerWidth < 1024) showSidebar = false;">
                            <div class="font-bold text-sm text-slate-800 group-hover:text-green-600 transition-colors">{{ $rs->name }}</div>
                            <div class="text-[11px] text-slate-500 mb-2 uppercase">{{ $rs->type ?? 'Rumah Sakit Umum' }}</div>
                            
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-100 w-max px-2.5 py-1 rounded-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-slate-400"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" /></svg>
                                    {{ $rs->phone ?? 'Tidak ada nomor' }}
                                </div>
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $rs->latitude }},{{ $rs->longitude }}" target="_blank" @click.stop class="text-[10px] font-bold bg-green-100 text-green-700 px-2.5 py-1.5 rounded-lg hover:bg-green-200 transition flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.705V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" /></svg>
                                    Rute
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center p-4 text-sm text-slate-500">Data Rumah Sakit Kosong.</div>
                    @endforelse
                </div>

                <div x-show="activeTab === 'police'" style="display: none;" x-transition.opacity>
                    @forelse($policeStations as $polisi)
                        <div class="bg-white p-3 mb-2 rounded-xl border border-slate-200 hover:border-blue-300 hover:shadow-md transition cursor-pointer group" @click="showFacilityOnMap({{ $polisi->id }}, 'police'); if(window.innerWidth < 1024) showSidebar = false;">
                            <div class="font-bold text-sm text-slate-800 group-hover:text-blue-600 transition-colors">{{ $polisi->name }}</div>
                            <div class="text-[11px] text-slate-500 mb-2 truncate">{{ $polisi->address ?? 'Kantor Polisi' }}</div>
                            
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-100 w-max px-2.5 py-1 rounded-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-slate-400"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" /></svg>
                                    {{ $polisi->phone ?? '110' }}
                                </div>
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $polisi->latitude }},{{ $polisi->longitude }}" target="_blank" @click.stop class="text-[10px] font-bold bg-blue-100 text-blue-700 px-2.5 py-1.5 rounded-lg hover:bg-blue-200 transition flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.705V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" /></svg>
                                    Rute
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center p-4 text-sm text-slate-500">Data Kepolisian Kosong.</div>
                    @endforelse
                </div>

                <div x-show="activeTab === 'fire'" style="display: none;" x-transition.opacity>
                    @forelse($fireStations as $damkar)
                        <div class="bg-white p-3 mb-2 rounded-xl border border-slate-200 hover:border-orange-300 hover:shadow-md transition cursor-pointer group" @click="showFacilityOnMap({{ $damkar->id }}, 'fire'); if(window.innerWidth < 1024) showSidebar = false;">
                            <div class="font-bold text-sm text-slate-800 group-hover:text-orange-600 transition-colors">{{ $damkar->name }}</div>
                            <div class="text-[11px] text-slate-500 mb-2 truncate">{{ $damkar->address ?? 'Pos Pemadam' }}</div>
                            
                            <div class="flex items-center justify-between mt-2">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 bg-slate-100 w-max px-2.5 py-1 rounded-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-slate-400"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5Z" clip-rule="evenodd" /></svg>
                                    {{ $damkar->phone ?? '113' }}
                                </div>
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $damkar->latitude }},{{ $damkar->longitude }}" target="_blank" @click.stop class="text-[10px] font-bold bg-orange-100 text-orange-700 px-2.5 py-1.5 rounded-lg hover:bg-orange-200 transition flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.705V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" /></svg>
                                    Rute
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center p-4 text-sm text-slate-500">Data Pemadam Kebakaran Kosong.</div>
                    @endforelse
                </div>

            </div>
        </div>

        <div x-show="showNotifications" style="display: none;" class="fixed inset-0 z-[600] flex justify-center items-center p-4">
            <div x-show="showNotifications" x-transition.opacity class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="showNotifications = false; $wire.markNotificationsAsRead()"></div>
            <div x-show="showNotifications" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl flex flex-col max-h-[85vh]">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                    <h2 class="font-bold text-slate-800 text-lg">Pesan Sistem</h2>
                    <button @click="showNotifications = false; $wire.markNotificationsAsRead()" class="p-1.5 bg-slate-200 text-slate-500 rounded-full hover:bg-slate-300"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>
                <div class="p-2 overflow-y-auto flex-1 bg-white">
                    @forelse($userNotifications as $notif)
                        <div class="p-4 rounded-xl border mb-2 {{ $notif->is_read ? 'bg-white border-slate-100' : 'bg-blue-50 border-blue-100' }}">
                            <div class="flex justify-between items-start mb-1"><h4 class="font-bold text-sm {{ $notif->is_read ? 'text-slate-700' : 'text-blue-800' }}">{{ $notif->title }}</h4><span class="text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($notif->created_at)->diffForHumans() }}</span></div>
                            <p class="text-xs text-slate-600 leading-relaxed">{{ $notif->message }}</p>
                        </div>
                    @empty
                        <div class="text-center p-8"><p class="text-slate-500 text-sm">Tidak ada notifikasi baru.</p></div>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-show="showHistory" style="display: none;" class="fixed inset-0 z-[600] flex justify-center items-center p-4">
            <div x-show="showHistory" x-transition.opacity class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="showHistory = false"></div>
            <div x-show="showHistory" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl flex flex-col max-h-[85vh]">
                <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                    <h2 class="font-bold text-slate-800 text-lg">Riwayat Darurat Anda</h2>
                    <button @click="showHistory = false" class="p-1.5 bg-slate-200 text-slate-500 rounded-full hover:bg-slate-300 transition"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button>
                </div>
                <div class="p-2 overflow-y-auto flex-1 bg-white">
                    @forelse($userHistory as $history)
                        <div class="bg-white p-4 rounded-xl border border-slate-100 mb-2 shadow-sm">
                            <div class="flex justify-between items-start mb-2"><span class="font-bold text-slate-800 uppercase text-sm flex items-center gap-2"><div class="w-2 h-2 rounded-full {{ $history->status === 'resolved' ? 'bg-slate-400' : 'bg-red-500 animate-pulse' }}"></div>{{ $history->emergency_type }}</span><span class="text-[10px] text-slate-400 font-medium">{{ \Carbon\Carbon::parse($history->triggered_at)->diffForHumans() }}</span></div>
                            <p class="text-xs text-slate-600 mb-3">{{ $history->notes ?: 'Tidak ada catatan tambahan.' }}</p>
                            <div class="flex justify-between items-center pt-2 border-t border-slate-50"><span class="text-[11px] font-bold px-2 py-1 rounded {{ $history->status === 'resolved' ? 'bg-slate-100 text-slate-500' : 'bg-red-50 text-red-600' }}">STATUS: {{ strtoupper($history->status) }}</span><button class="text-[11px] text-indigo-600 font-semibold" @click="showHistory = false; if(leafletMap) leafletMap.flyTo([{{ $history->latitude }}, {{ $history->longitude }}], 17);">Lihat Lokasi</button></div>
                        </div>
                    @empty
                        <div class="text-center p-8"><p class="text-slate-500 text-sm">Belum ada riwayat laporan darurat.</p></div>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-data="{ open: @entangle('showEmergencyModal') }" x-show="open" style="display: none;" class="fixed inset-0 z-[600] flex items-end sm:items-center justify-center">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" @click="open = false"></div>
            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-8 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-8 sm:scale-95" class="relative w-full max-w-lg bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                <div class="p-5 sm:p-6 overflow-y-auto">
                    <div class="flex justify-between items-center mb-6"><div><h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">Kirim Sinyal SOS</h2><p class="text-slate-500 text-xs mt-1">Sistem melacak koordinat Anda secara otomatis.</p></div><button @click="open = false" class="p-2 bg-slate-100 text-slate-500 rounded-full hover:bg-slate-200 transition"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button></div>
                    <form wire:submit="submitEmergency">
                        <div class="mb-6 p-3 rounded-xl border {{ $is_gps_locked ? 'bg-green-50 border-green-200' : 'bg-slate-50 border-slate-200' }} flex items-center gap-3">
                            @if($is_gps_locked)
                                <span class="flex h-3 w-3 relative"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span></span><div class="text-xs"><span class="font-bold text-green-700 block">Koordinat Terkunci</span><span class="text-green-600">{{ $latitude }}, {{ $longitude }}</span></div>
                            @else
                                <svg class="animate-spin h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span class="text-xs font-medium text-slate-600">{{ $gps_status }}</span>
                            @endif
                            <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                        </div>
                        <div class="mb-6" x-data="{ selectedType: @entangle('emergency_type') }">
                            <label class="block text-sm font-bold text-slate-700 mb-2">Jenis Darurat</label>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach(['Medis', 'Kebakaran', 'Kriminal', 'Lainnya'] as $t)
                                    <button type="button" @click="selectedType = '{{ $t }}'" :class="selectedType === '{{ $t }}' ? 'border-red-600 bg-red-50 text-red-600 shadow-sm transform scale-105' : 'border-slate-100 bg-white text-slate-400 hover:border-slate-200'" class="flex flex-col items-center justify-center py-3 px-1 rounded-xl border-2 transition-all"><span class="font-bold text-[11px] uppercase mt-1">{{ $t }}</span></button>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('emergency_type')" class="mt-1 text-xs" />
                        </div>
                        <div class="mb-6">
                            <label for="notes" class="block text-sm font-bold text-slate-700 mb-2">Keterangan Tambahan</label>
                            <textarea wire:model="notes" id="notes" rows="2" class="block w-full border-slate-200 bg-slate-50 focus:bg-white focus:border-red-500 focus:ring-red-500 rounded-xl shadow-sm text-sm p-3" placeholder="Opsional: Jelaskan situasi..."></textarea>
                        </div>
                        <button type="submit" wire:loading.attr="disabled" class="w-full py-4 bg-slate-950 border border-transparent rounded-xl font-black text-white uppercase tracking-widest hover:bg-slate-800 active:scale-[0.98] transition-all flex justify-center items-center gap-2 shadow-lg disabled:opacity-75 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="submitEmergency">PANGGIL BANTUAN SEKARANG</span>
                            <span wire:loading wire:target="submitEmergency" class="flex items-center gap-2"><svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>MENGIRIM SINYAL...</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <script>
            let leafletMap; 
            
            // Simpan Data di Global Object Array
            window.facilityData = {
                'hospital': @json($hospitals),
                'police': @json($policeStations),
                'fire': @json($fireStations)
            };
            
            let activeFacilityMarker = null; 

            // Definisi 3 Icon Berbeda sesuai Fasilitas
            var hospitalIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background-color:#10b981; width:26px; height:26px; border-radius:50%; border:3px solid white; box-shadow: 0 0 10px rgba(16,185,129,0.5); display:flex; align-items:center; justify-content:center;'><b style='color:white; font-size:14px;'>H</b></div>", iconSize: [26, 26], iconAnchor: [13, 13] });
            var policeIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background-color:#3b82f6; width:26px; height:26px; border-radius:50%; border:3px solid white; box-shadow: 0 0 10px rgba(59,130,246,0.5); display:flex; align-items:center; justify-content:center;'><b style='color:white; font-size:14px;'>P</b></div>", iconSize: [26, 26], iconAnchor: [13, 13] });
            var fireIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background-color:#f97316; width:26px; height:26px; border-radius:50%; border:3px solid white; box-shadow: 0 0 10px rgba(249,115,22,0.5); display:flex; align-items:center; justify-content:center;'><b style='color:white; font-size:14px;'>F</b></div>", iconSize: [26, 26], iconAnchor: [13, 13] });
            
            document.addEventListener('livewire:initialized', () => {
                leafletMap = L.map('map', { zoomControl: false }).setView([-6.4025, 106.8227], 12);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 }).addTo(leafletMap);
                L.control.zoom({ position: 'bottomright' }).addTo(leafletMap);

                var activeEmergencyIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background-color:#ef4444; width:20px; height:20px; border-radius:50%; border:3px solid white; box-shadow: 0 0 10px rgba(239,68,68,0.8); animation: pulse-red 2s infinite;'></div>", iconSize: [20, 20], iconAnchor: [10, 10] });
                var resolvedEmergencyIcon = L.divIcon({ className: 'custom-div-icon', html: "<div style='background-color:#94a3b8; width:16px; height:16px; border-radius:50%; border:2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.2);'></div>", iconSize: [16, 16], iconAnchor: [8, 8] });
                var userMarker = null;

                if (navigator.geolocation) {
                    navigator.geolocation.watchPosition(
                        (position) => {
                            let lat = position.coords.latitude;
                            let lng = position.coords.longitude;
                            @this.set('latitude', lat); @this.set('longitude', lng); @this.set('is_gps_locked', true); @this.set('gps_status', 'Sinyal Terkunci');
                            if(userMarker) { userMarker.setLatLng([lat, lng]); } else { userMarker = L.circleMarker([lat, lng], { radius: 8, color: 'white', weight: 2, fillColor: '#3b82f6', fillOpacity: 1 }).addTo(leafletMap).bindPopup("<b>Lokasi Anda</b>"); }
                        },
                        (error) => { @this.set('gps_status', 'Gagal akses GPS.'); },
                        { enableHighAccuracy: true, maximumAge: 10000 }
                    );
                }

                let emergencies = @json($emergencyHistory);
                emergencies.forEach(e => {
                    let iconUsed = (e.status === 'resolved') ? resolvedEmergencyIcon : activeEmergencyIcon;
                    let popupContent = `<b class='text-slate-800'>Insiden: ${e.emergency_type}</b><br><span class='text-xs'>Status: ${(e.status === 'resolved') ? 'Selesai' : 'Aktif'}</span>`;
                    L.marker([e.latitude, e.longitude], {icon: iconUsed}).addTo(leafletMap).bindPopup(popupContent);
                });

                Livewire.on('emergency-created', (data) => {
                    let info = data[0];
                    L.marker([info.lat, info.lng], {icon: activeEmergencyIcon}).addTo(leafletMap).bindPopup("<b class='text-red-600'>Darurat Baru: " + info.type + "</b>").openPopup();
                    leafletMap.flyTo([info.lat, info.lng], 16);
                });
            });

            // FUNGSI UPDATE: Menerima tipe fasilitas (hospital, police, fire) untuk menyesuaikan data dan ikonnya
            function showFacilityOnMap(id, type) {
                if(!leafletMap) return;
                
                let facilityArray = window.facilityData[type];
                let item = facilityArray.find(h => h.id === id);
                
                if(item) {
                    if(activeFacilityMarker) { leafletMap.removeLayer(activeFacilityMarker); }
                    
                    let useIcon = hospitalIcon;
                    let colorName = 'text-green-600';
                    let labelTipe = item.type ?? 'Fasilitas';
                    
                    if(type === 'police') { useIcon = policeIcon; colorName = 'text-blue-600'; labelTipe = 'Kantor Polisi'; }
                    if(type === 'fire') { useIcon = fireIcon; colorName = 'text-orange-600'; labelTipe = 'Pos Pemadam'; }

                    let popupContent = `<b class='${colorName}'>${item.name}</b><br>
                                        <span class='text-[10px] text-slate-500 uppercase'>${labelTipe}</span><br>
                                        <span class='text-xs font-bold'>Telp: ${item.phone || '-'}</span>`;

                    activeFacilityMarker = L.marker([item.latitude, item.longitude], {icon: useIcon})
                        .addTo(leafletMap).bindPopup(popupContent).openPopup(); 
                    
                    leafletMap.flyTo([item.latitude, item.longitude], 17); 
                }
            }
        </script>
    </div>
</div>