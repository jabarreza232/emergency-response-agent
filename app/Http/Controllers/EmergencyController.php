<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EmergencyController extends Controller
{
    public function index()
    {
        // Memanggil view yang berisi komponen livewire
        return view('livewire.emergency_dashboard');
    }

    public function trigger()
    {
        return view('livewire.emergency_trigger');
    }
}
