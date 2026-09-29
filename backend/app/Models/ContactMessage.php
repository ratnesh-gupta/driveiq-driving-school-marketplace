<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = [
        'user_id', 'name', 'email', 'subject', 'message', 'status', 'ip_address', 'handled_at', 'handled_by',
    ];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
