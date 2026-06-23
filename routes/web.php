<?php

use App\Http\Controllers\EmergencyController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
// use App\Livewire\EmergencyDashboard;
// use App\Livewire\EmergencyTrigger;
Volt::route('/', 'pages.auth.login');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


Route::get('/test-dashboard', [EmergencyController::class, 'index']);
Route::get('/test-trigger', [EmergencyController::class, 'trigger']);

Route::middleware(['auth'])->group(function () {
    Route::view('profile', 'profile')
        ->name('profile');
    Volt::route('/emergency/dashboard', 'emergency_dashboard')
        ->name('emergency.dashboard');
    Volt::route('/reporter/dashboard', 'reporter_dashboard')
        ->name('reporter.dashboard');
    Volt::route('/emergency/trigger', 'emergency_trigger')
        ->name('emergency.trigger');
    Volt::route('/emergency/contacts', 'emergency_contacts')
        ->name('emergency.contacts');

    Volt::route('/admin/users', 'admin.user-management')->name('admin.users');
    Volt::route('/admin/facilities', 'admin.facility-management')->name('admin.facilities');
});
require __DIR__ . '/auth.php';
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
