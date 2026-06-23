<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->form->validate();

        $this->form->authenticate();

        Session::regenerate();

        $user = auth()->user();

        // Ganti redirectIntended menjadi redirect biasa untuk memaksa arah sesuai role
        if ($user->role_id === 1) { 
            $this->redirect(route('emergency.dashboard', absolute: false), navigate: true);
        } else {
            $this->redirect(route('reporter.dashboard', absolute: false), navigate: true);
        }
    }
}; ?>

<div class="min-h-screen flex bg-white sm:bg-slate-50 lg:bg-white">
    <!-- Sisi Kiri: Branding & Visual (Hanya tampil di layar besar/Laptop) -->
    <div class="hidden lg:flex lg:w-1/2 bg-slate-950 p-12 xl:p-20 flex-col justify-between relative overflow-hidden">
        <!-- Pattern Background -->
        <svg class="absolute inset-0 opacity-10" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="dotPattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                    <circle cx="2" cy="2" r="1" fill="#fff"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#dotPattern)"/>
        </svg>

        <div class="relative z-10 flex items-center gap-3">
            <div class="p-2.5 rounded-xl bg-red-600 text-white shadow-lg shadow-red-600/20">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-8 h-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                </svg>
            </div>
            <span class="text-2xl font-bold text-white tracking-tight">ERA <span class="text-slate-400 font-medium">Portal</span></span>
        </div>

        <div class="relative z-10 mt-12 xl:mt-20">
            <h1 class="text-4xl xl:text-5xl font-extrabold text-white leading-tight tracking-tighter">
                Rapid <span class="text-red-500">Response</span>,<br> Just One <span class="text-red-500">Tap</span> Away.
            </h1>
            <p class="mt-6 text-base xl:text-lg text-slate-300 max-w-md leading-relaxed">
                Platform terintegrasi untuk Agen Respons Darurat. Masuk untuk memantau situasi atau membuat laporan darurat instan.
            </p>
        </div>

        <div class="relative z-10 text-sm text-slate-500">
            &copy; {{ date('Y') }} ERA Systems. Sentiasa Bersiap Sedia.
        </div>
    </div>

    <!-- Sisi Kanan: Form Login (Responsif menyesuaikan HP) -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-6 sm:p-12 xl:p-20">
        <!-- Kotak form dibuat memusat dan ada bayangan sedikit jika di tampilan HP -->
        <div class="w-full max-w-md mx-auto bg-white sm:shadow-xl sm:ring-1 sm:ring-slate-900/5 sm:rounded-3xl sm:p-10 lg:shadow-none lg:ring-0 lg:bg-transparent lg:p-0 transition-all">
            
            <!-- Header Mobile (Tampil logo jika dibuka via HP) -->
            <div class="flex items-center gap-3 mb-8 lg:hidden justify-center sm:justify-start">
                 <div class="p-2 rounded-lg bg-red-600 text-white">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                    </svg>
                </div>
                <span class="text-2xl font-bold text-slate-950">ERA Portal</span>
            </div>

            <div class="text-center sm:text-left">
                <h2 class="text-3xl font-extrabold text-slate-950 tracking-tighter">{{ __('Selamat Datang') }}</h2>
                <p class="text-slate-500 mt-2 text-sm sm:text-base">{{ __('Silakan masuk ke akun Anda untuk melanjutkan.') }}</p>
            </div>

            <x-auth-session-status class="mt-4" :status="session('status')" />

            <form wire:submit="login" class="mt-8 space-y-6">
                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Alamat Email') }}</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H4.5a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username" 
                            class="block w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition duration-150 text-slate-900 placeholder:text-slate-400 text-sm sm:text-base" 
                            placeholder="nama@email.com">
                    </div>
                    <x-input-error :messages="$errors->get('form.email')" class="mt-2 text-xs" />
                </div>

                <!-- Password -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-sm font-semibold text-slate-700">{{ __('Kata Sandi') }}</label>
                        @if (Route::has('password.request'))
                            <a class="text-xs font-bold text-red-600 hover:text-red-700 transition" href="{{ route('password.request') }}" wire:navigate>
                                {{ __('Lupa sandi?') }}
                            </a>
                        @endif
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>
                        <input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password" 
                            class="block w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition duration-150 text-slate-900 placeholder:text-slate-400 text-sm sm:text-base" 
                            placeholder="••••••••">
                    </div>
                    <x-input-error :messages="$errors->get('form.password')" class="mt-2 text-xs" />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between">
                    <label for="remember" class="inline-flex items-center group cursor-pointer">
                        <input wire:model="form.remember" id="remember" type="checkbox" 
                            class="rounded border-slate-300 text-red-600 shadow-sm focus:ring-red-500 focus:ring-offset-0 h-4 w-4 sm:h-5 sm:w-5 transition cursor-pointer" name="remember">
                        <span class="ms-2.5 text-xs sm:text-sm text-slate-600 group-hover:text-slate-900 transition">{{ __('Ingat perangkat ini') }}</span>
                    </label>
                </div>

                <!-- Tombol Login -->
                <div class="pt-2">
                    <button type="submit" 
                        class="w-full flex justify-center items-center gap-3 px-6 py-3.5 bg-slate-950 border border-transparent rounded-xl font-bold text-sm sm:text-base text-white uppercase tracking-widest hover:bg-slate-800 focus:bg-slate-800 active:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-950 focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50 group shadow-lg shadow-slate-900/20">
                        
                        <svg wire:loading wire:target="login" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>

                        <span wire:loading.remove wire:target="login">{{ __('Masuk Aman') }}</span>
                        <span wire:loading wire:target="login">{{ __('Memproses...') }}</span>
                        
                        <svg wire:loading.remove wire:target="login" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 group-hover:translate-x-1 transition-transform">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>
            </form>
            
            <div class="mt-8 text-center text-xs sm:text-sm text-slate-500">
                Belum memiliki akses? <a href="#" class="font-bold text-red-600 hover:text-red-700 transition">Hubungi Admin Sistem</a>
            </div>
        </div>
    </div>
</div>