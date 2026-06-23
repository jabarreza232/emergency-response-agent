<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="max-w-2xl mx-auto bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-100">
    
    <!-- HEADER NAVIGASI KEMBALI -->
    <div class="mb-8 pb-4 border-b border-slate-100">
        <a href="{{ auth()->user()->role_id === 1 ? route('emergency.dashboard') : route('reporter.dashboard') }}" 
           wire:navigate 
           class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-red-600 transition-colors group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 group-hover:-translate-x-1 transition-transform">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            {{ __('Kembali ke Dashboard') }}
        </a>
    </div>

    <!-- JUDUL HALAMAN -->
    <header class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div class="p-2 rounded-xl bg-slate-100 text-slate-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                {{ __('Informasi Profil') }}
            </h2>
        </div>
        <p class="text-sm text-slate-500 leading-relaxed">
            {{ __("Perbarui nama lengkap dan alamat email akun ERA Portal Anda di sini.") }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="space-y-6">
        
        <!-- Input Nama -->
        <div>
            <label for="name" class="block text-sm font-bold text-slate-700 mb-2">{{ __('Nama Lengkap') }}</label>
            <input wire:model="name" id="name" name="name" type="text" 
                class="block w-full border-slate-200 bg-slate-50 focus:bg-white focus:border-red-500 focus:ring-red-500/20 rounded-xl shadow-sm text-sm p-3.5 transition-colors text-slate-900 placeholder:text-slate-400" 
                required autofocus autocomplete="name" placeholder="Masukkan nama Anda" />
            <x-input-error class="mt-2 text-xs text-red-600 font-medium" :messages="$errors->get('name')" />
        </div>

        <!-- Input Email -->
        <div>
            <label for="email" class="block text-sm font-bold text-slate-700 mb-2">{{ __('Alamat Email') }}</label>
            <input wire:model="email" id="email" name="email" type="email" 
                class="block w-full border-slate-200 bg-slate-50 focus:bg-white focus:border-red-500 focus:ring-red-500/20 rounded-xl shadow-sm text-sm p-3.5 transition-colors text-slate-900 placeholder:text-slate-400" 
                required autocomplete="username" placeholder="nama@email.com" />
            <x-input-error class="mt-2 text-xs text-red-600 font-medium" :messages="$errors->get('email')" />

            <!-- Peringatan Verifikasi Email -->
            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="mt-4 p-4 rounded-xl bg-orange-50 border border-orange-100">
                    <p class="text-sm text-orange-800 flex items-center gap-2 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 text-orange-500">
                            <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" clip-rule="evenodd" />
                        </svg>
                        {{ __('Alamat email Anda belum diverifikasi.') }}
                    </p>
                    <button wire:click.prevent="sendVerification" class="mt-2 ml-7 text-sm font-bold text-orange-600 hover:text-orange-700 transition underline underline-offset-4 focus:outline-none">
                        {{ __('Klik di sini untuk mengirim ulang tautan verifikasi.') }}
                    </button>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-4 ml-7 font-bold text-xs text-green-700 bg-green-100 p-2 rounded-lg inline-flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                                <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                            </svg>
                            {{ __('Tautan verifikasi baru telah dikirim.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-6 border-t border-slate-100 mt-8">
            
            <!-- Tombol Simpan -->
            <button type="submit" wire:loading.attr="disabled"
                class="px-6 py-3.5 bg-slate-950 border border-transparent rounded-xl font-black text-xs text-white uppercase tracking-widest hover:bg-slate-800 active:scale-[0.98] transition-all shadow-md disabled:opacity-75 disabled:cursor-not-allowed flex items-center justify-center min-w-[160px]">
                
                <span wire:loading.remove wire:target="updateProfileInformation">
                    {{ __('SIMPAN PERUBAHAN') }}
                </span>
                
                <span wire:loading wire:target="updateProfileInformation" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    MENYIMPAN...
                </span>
            </button>

            <!-- Pesan Sukses -->
            <x-action-message class="me-3" on="profile-updated">
                <span x-transition.opacity class="flex items-center gap-1.5 text-sm font-bold text-green-700 bg-green-50 border border-green-100 px-3 py-2 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                        <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                    </svg>
                    {{ __('Profil diperbarui.') }}
                </span>
            </x-action-message>
        </div>
    </form>
</section>