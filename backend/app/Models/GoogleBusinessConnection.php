<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** DIQ-1107: a school's connected Google Business Profile (tokens encrypted). */
class GoogleBusinessConnection extends Model
{
    protected $fillable = [
        'school_id', 'user_id', 'access_token', 'refresh_token', 'token_expires_at',
        'location_name', 'location_title', 'place_id', 'rating', 'review_count', 'imported_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'imported_at' => 'datetime',
            'rating' => 'float',
        ];
    }
}
