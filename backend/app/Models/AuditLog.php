<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'model_type',
        'model_id',
        'action',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public static function log(
        string $action,
        string $modelType,
        ?int $modelId = null,
        array $oldValues = [],
        array $newValues = []
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'school_id' => auth()->user()?->school_id,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $action,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
