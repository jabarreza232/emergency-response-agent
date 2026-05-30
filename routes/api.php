<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Events\EmergencyTriggered;
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/supabase-webhook', function (Request $request) {
    $data = $request->input('record');
    
    if ($data) {
        // Tambahkan Log ini
        \Illuminate\Support\Facades\Log::info('Triggering Event for Reverb...', $data);
        
        // Gunakan broadcast() bukan event() untuk memastikan ShouldBroadcast terpanggil
        broadcast(new \App\Events\EmergencyTriggered($data));
        
        return response()->json(['status' => 'Event Dispatched']);
    }
    return "No Data";
});