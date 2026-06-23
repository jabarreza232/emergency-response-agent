<div class="min-h-screen bg-slate-900 pb-12" x-data="{ isModalOpen: @entangle('isModalOpen') }">
    {{-- TOPBAR --}}
    <div class="bg-slate-950/90 backdrop-blur-md px-4 py-4 sm:px-8 shadow-md flex justify-between items-center border-b border-slate-800 mb-8 sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-bold text-white tracking-tight">Manajemen <span class="text-slate-400 font-medium">Warga</span></h1>
        </div>
        <a href="{{ route('emergency.dashboard') }}" wire:navigate class="text-slate-300 hover:text-white text-sm font-semibold transition flex items-center gap-2 bg-slate-800/50 px-4 py-2 rounded-full border border-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" /></svg>
            Kembali ke Dasbor
        </a>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        @if (session('status'))
            <div class="mb-6 bg-emerald-500/20 border border-emerald-500/50 text-emerald-400 p-4 rounded-xl shadow-lg flex justify-between items-start animate-pulse">
                <span class="font-bold text-sm">✅ {{ session('status') }}</span>
            </div>
        @endif

        <div class="bg-slate-800 rounded-3xl shadow-xl border border-slate-700 overflow-hidden">
            <div class="p-6 border-b border-slate-700 flex justify-between items-center bg-slate-800/50">
                <h2 class="text-lg font-bold text-white">Daftar Akun Pelapor</h2>
                
                {{-- Tombol Tambah (Menggunakan Alpine untuk Buka Instan) --}}
                <button @click="isModalOpen = true; $wire.openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Tambah Warga
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/50 text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-4 font-bold">Nama Lengkap</th>
                            <th class="px-6 py-4 font-bold">Alamat Email</th>
                            <th class="px-6 py-4 font-bold">Bergabung Pada</th>
                            <th class="px-6 py-4 font-bold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/50">
                        @forelse($users as $u)
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="px-6 py-4 font-semibold text-white">{{ $u->name }}</td>
                            <td class="px-6 py-4">{{ $u->email }}</td>
                            <td class="px-6 py-4">{{ $u->created_at->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                
                                {{-- Tombol Edit (Buka Modal Instan via Alpine) --}}
                                <button @click="isModalOpen = true; $wire.openModal({{ $u->id }})" class="text-blue-400 hover:text-blue-300 font-semibold px-3 py-1.5 bg-blue-500/10 rounded-lg transition min-w-[60px]">
                                    Edit
                                </button>

                                {{-- Tombol Hapus --}}
                                <button wire:click="deleteUser({{ $u->id }})" wire:confirm="Yakin ingin menghapus warga ini?" wire:loading.attr="disabled" class="text-red-400 hover:text-red-300 font-semibold px-3 py-1.5 bg-red-500/10 rounded-lg disabled:opacity-50 transition min-w-[70px]">
                                    <span wire:loading.remove wire:target="deleteUser({{ $u->id }})">Hapus</span>
                                    <span wire:loading wire:target="deleteUser({{ $u->id }})" class="inline-block animate-pulse">...</span>
                                </button>
                                
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">Tidak ada data pengguna.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-slate-700">{{ $users->links() }}</div>
        </div>
    </div>

    {{-- MODAL FORM USER (Dikontrol penuh oleh Alpine x-show) --}}
    <div x-show="isModalOpen" style="display: none;" class="fixed inset-0 z-[600] flex items-center justify-center p-4">
        
        {{-- Backdrop hitam transparan --}}
        <div x-show="isModalOpen" x-transition.opacity class="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" @click="isModalOpen = false"></div>
        
        {{-- Kotak Konten Modal --}}
        <div x-show="isModalOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" 
             class="relative bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md border border-slate-700 p-6 overflow-hidden">
            
            {{-- INDIKATOR LOADING INTERNAL: Hanya menutupi form ketika mengambil data user lama dari server --}}
            <div wire:loading wire:target="openModal" class="absolute inset-0 z-50 bg-slate-900/90 backdrop-blur-sm flex items-center justify-center">
                <div class="flex flex-col items-center gap-3">
                    <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="text-sm font-bold text-slate-300">Menyiapkan Form...</span>
                </div>
            </div>

            <h3 class="text-lg font-bold text-white mb-4">{{ $userId ? 'Edit Data Warga' : 'Tambah Warga Baru' }}</h3>
            
            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Nama Lengkap</label>
                    <input type="text" wire:model="name" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500 shadow-inner">
                    @error('name') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Email Aktif</label>
                    <input type="email" wire:model="email" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500 shadow-inner">
                    @error('email') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Kata Sandi {{ $userId ? '(Kosongkan jika tidak diubah)' : '' }}</label>
                    <input type="password" wire:model="password" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-indigo-500 shadow-inner">
                    @error('password') <span class="text-red-400 text-xs mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" @click="isModalOpen = false" class="flex-1 px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl font-bold transition">Batal</button>
                    
                    {{-- Tombol Simpan --}}
                    <button type="submit" wire:loading.attr="disabled" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-bold transition flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="save">Simpan</span>
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