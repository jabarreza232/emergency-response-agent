<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $log;

    /**
     * Create a new event instance.
     * $log akan berisi data dari tabel emergency_logs
     */
    public function __construct($log)
    {
        $this->log = $log;
    }

    /**
     * Nama Channel yang akan di-listen oleh Frontend.
     * Di sini kita gunakan Public Channel agar mudah untuk testing awal.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('emergency-channel'),
        ];
    }

    /**
     * Nama event yang akan diterima di Laravel Echo.
     * Defaultnya adalah nama class, tapi bisa dicustom.
     */
    public function broadcastAs(): string
    {
        return 'emergency.triggered';
    }

    /**
     * Data apa saja yang ingin dikirim ke Reverb.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->log['id'] ?? null,
            'type' => $this->log['emergency_type'] ?? 'General',
            'latitude' => $this->log['latitude'] ?? 0,
            'longitude' => $this->log['longitude'] ?? 0,
            'notes' => $this->log['notes'] ?? '',
            'triggered_at' => now()->toDateTimeString(),
        ];
    }
}
