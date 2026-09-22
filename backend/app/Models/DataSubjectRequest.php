<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataSubjectRequest extends Model
{
    protected $fillable = [
        'name',
        'email',
        'request_type',
        'details',
        'nominee_name',
        'nominee_email',
        'nominee_relation',
        'status',
        'ip_address',
        'user_id',
        'handled_at',
        'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
