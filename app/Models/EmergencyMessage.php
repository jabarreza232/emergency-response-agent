<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyMessage extends Model
{
    protected $fillable = ['emergency_log_id', 'user_id', 'message'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
