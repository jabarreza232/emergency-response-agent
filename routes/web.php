<?php
use App\Http\Controllers\EmergencyController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
// use App\Livewire\EmergencyDashboard;
// use App\Livewire\EmergencyTrigger;
Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');
Route::get('/test-dashboard', [EmergencyController::class, 'index']);
Route::get('/test-trigger', [EmergencyController::class, 'trigger']);
Volt::route('/emergency/dashboard', 'emergency_dashboard')
    ->name('emergency.dashboard')
    ->middleware(['auth']);
    Volt::route('/emergency/trigger', 'emergency_trigger')
    ->name('emergency.trigger')
    ->middleware(['auth']);
    Volt::route('/emergency/contacts', 'emergency_contacts')
    ->name('emergency.contacts')
    ->middleware(['auth']);
require __DIR__.'/auth.php';
Route::get('/test-broadcast', function () {
    $data = [
        'id' => rand(1, 100),
        'emergency_type' => 'Test',
        'latitude' => -6.123,
        'longitude' => 106.123,
        'notes' => 'Testing dari browser',
        'triggered_at' => now()->toDateTimeString()
    ];
    
    broadcast(new \App\Events\EmergencyTriggered($data));
    
    return "Event dikirim ke Reverb!";
});