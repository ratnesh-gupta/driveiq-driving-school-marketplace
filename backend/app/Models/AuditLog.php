<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit trail. Rows cannot be updated or deleted through Eloquent,
 * and a PostgreSQL trigger enforces the same at the database level (the only
 * permitted update is the FK nullOnDelete of user_id / school_id).
 */
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

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit log entries are immutable.'));
    }

    /**
     * @param  int|null  $schoolId  school the change belongs to; defaults to the
     *                              actor's school (pass it when an admin acts on a school)
     */
    public static function log(
        string $action,
        string $modelType,
        ?int $modelId = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $schoolId = null,
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'school_id' => $schoolId ?? auth()->user()?->school_id,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'action' => $action,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
