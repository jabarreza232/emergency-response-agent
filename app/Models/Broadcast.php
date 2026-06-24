<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
protected $fillable = ['title', 'message', 'level', 'is_active'];
}
