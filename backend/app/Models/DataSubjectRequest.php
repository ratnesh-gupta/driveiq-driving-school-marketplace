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
        'verification_token_hash',
        'verification_expires_at',
        'verified_at',
        'due_at',
        'erased_at',
    ];

    protected $hidden = ['verification_token_hash'];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'due_at' => 'datetime',
            'erased_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
