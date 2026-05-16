<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id', 'name', 'phone', 'email', 'vehicle_type', 'area',
        'preferred_timing', 'channel', 'message', 'status',
    ];
}
