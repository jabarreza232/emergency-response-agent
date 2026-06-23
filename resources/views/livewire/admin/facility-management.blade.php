<div class="min-h-screen bg-slate-900 pb-12" x-data="{ isModalOpen: @entangle('isModalOpen') }">
    {{-- TOPBAR --}}
    <div class="bg-slate-950/90 backdrop-blur-md px-4 py-4 sm:px-8 shadow-md flex justify-between items-center border-b border-slate-800 mb-8 sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-white tracking-tight">Manajemen <span class="text-slate-400 font-medium">Fasilitas</span></h1>
        </div>
        <a href="{{ route('emergency.dashboard') }}" wire:navigate class="text-slate-300 hover:text-white text-sm font-semibold transition flex items-center gap-2 bg-slate-800/50 px-4 py-2 rounded-full border border-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
            Kembali ke Dasbor
        </a>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        @if (session('status'))
            <div class="mb-6 bg-emerald-500/20 border border-emerald-500/50 text-emerald-400 p-4 rounded-xl shadow-lg flex justify-between items-start">
                <span class="font-bold text-sm">✅ {{ session('status') }}</span>
            </div>
        @endif

        {{-- TAB NAVIGASI --}}
        <div class="flex gap-2 mb-6">
            <button wire:click="switchTab('hospital')" wire:loading.attr="disabled" class="px-6 py-2.5 rounded-full text-sm font-bold transition-colors disabled:opacity-50 {{ $activeTab === 'hospital' ? 'bg-green-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white' }}">
                <span wire:loading.remove wire:target="switchTab('hospital')">🏥 Rumah Sakit</span>
                <span wire:loading wire:target="switchTab('hospital')" class="inline-block animate-pulse">Memuat...</span>
            </button>
            <button wire:click="switchTab('police')" wire:loading.attr="disabled" class="px-6 py-2.5 rounded-full text-sm font-bold transition-colors disabled:opacity-50 {{ $activeTab === 'police' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white' }}">
                <span wire:loading.remove wire:target="switchTab('police')">🚓 Kepolisian</span>
                <span wire:loading wire:target="switchTab('police')" class="inline-block animate-pulse">Memuat...</span>
            </button>
            <button wire:click="switchTab('fire')" wire:loading.attr="disabled" class="px-6 py-2.5 rounded-full text-sm font-bold transition-colors disabled:opacity-50 {{ $activeTab === 'fire' ? 'bg-orange-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white' }}">
                <span wire:loading.remove wire:target="switchTab('fire')">🚒 Pemadam Kebakaran</span>
                <span wire:loading wire:target="switchTab('fire')" class="inline-block animate-pulse">Memuat...</span>
            </button>
        </div>

        <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 overflow-hidden relative">
            
            {{-- Global Tabel Loading Overlay (Opsional) --}}
            <div wire:loading.flex wire:target="switchTab, gotoPage, nextPage, previousPage" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm z-20 items-center justify-center hidden">
                 <svg class="animate-spin h-8 w-8 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>

            <div class="p-6 border-b border-slate-700 flex justify-between items-center bg-slate-800/50">
                <h2 class="text-lg font-bold text-white uppercase tracking-wider">Daftar {{ $activeTab }}</h2>
                
                {{-- Tombol Tambah (Diubah ke Alpine: 100% Instan) --}}
                <button @click="isModalOpen = true; $wire.openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Tambah Fasilitas
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-4 font-bold">Nama Fasilitas</th>
                            <th class="px-6 py-4 font-bold">Kontak</th>
                            <th class="px-6 py-4 font-bold">Koordinat (Lat, Lng)</th>
                            <th class="px-6 py-4 font-bold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        @forelse($facilities as $f)
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="px-6 py-4 font-semibold text-white">
                                {{ $f->name }}
                                @if($activeTab === 'hospital' && $f->type)
                                    <span class="block text-[10px] text-slate-500 uppercase mt-0.5">{{ $f->type }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">{{ $f->phone ?? '-' }}</td>
                            <td class="px-6 py-4 text-xs font-mono">{{ $f->latitude }}, {{ $f->longitude }}</td>
                            <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                
                                {{-- Tombol Edit (Langsung memunculkan modal sambil background loading) --}}
                                <button @click="isModalOpen = true; $wire.openModal({{ $f->id }})" class="text-blue-400 hover:text-blue-300 font-semibold px-3 py-1.5 bg-blue-500/10 rounded-lg transition min-w-[60px]">
                                    Edit
                                </button>

                                <button wire:click="deleteFacility({{ $f->id }})" wire:confirm="Yakin ingin menghapus fasilitas ini?" wire:loading.attr="disabled" class="text-red-400 hover:text-red-300 font-semibold px-3 py-1.5 bg-red-500/10 rounded-lg disabled:opacity-50 transition min-w-[70px]">
                                    <span wire:loading.remove wire:target="deleteFacility({{ $f->id }})">Hapus</span>
                                    <span wire:loading wire:target="deleteFacility({{ $f->id }})" class="inline-block animate-pulse">...</span>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Tidak ada data fasilitas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-700 relative z-10">{{ $facilities->links() }}</div>
        </div>
    </div>

    {{-- MODAL FORM FASILITAS (Dengan Alpine x-show untuk transisi instan) --}}
    <div x-show="isModalOpen" style="display: none;" class="fixed inset-0 z-[600] flex items-center justify-center p-4">
        
        <div x-show="isModalOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" @click="isModalOpen = false"></div>
        
        <div x-show="isModalOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" 
             class="relative bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-700 p-6 overflow-hidden">
            
            {{-- LOADING OVERLAY KHUSUS DI DALAM MODAL (Hanya muncul saat mengambil data Edit) --}}
            <div wire:loading wire:target="openModal" class="absolute inset-0 z-50 bg-slate-900/90 backdrop-blur-sm flex items-center justify-center">
                <div class="flex flex-col items-center gap-3">
                    <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-sm font-bold text-slate-300">Menyiapkan Form...</span>
                </div>
            </div>

            <h3 class="text-lg font-bold text-white mb-4">{{ $facilityId ? 'Edit Fasilitas' : 'Tambah Fasilitas Baru' }}</h3>
            
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Nama Instansi/Fasilitas</label>
                    <input type="text" wire:model="name" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500">
                    @error('name') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                
                @if($activeTab === 'hospital')
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Tipe (Khusus RS)</label>
                    <input type="text" wire:model="type" placeholder="Misal: RSUD, RS Swasta, Klinik" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500">
                    @error('type') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Latitude</label>
                        <input type="text" wire:model="latitude" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500">
                        @error('latitude') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Longitude</label>
                        <input type="text" wire:model="longitude" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500">
                        @error('longitude') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Nomor Telepon Darurat</label>
                    <input type="text" wire:model="phone" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Alamat Lengkap</label>
                    <textarea wire:model="address" rows="2" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" @click="isModalOpen = false" class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl font-bold transition">Batal</button>
                    
                    <button type="submit" wire:loading.attr="disabled" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold transition flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save">Simpan Data</span>
                        <span wire:loading wire:target="save" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Menyimpan...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>