<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrivePackage extends Model
{
    use HasFactory;

    protected $table = 'packages';

    protected $fillable = [
        'school_id', 'name', 'description', 'price', 'sessions',
        'vehicle_type', 'transmission', 'has_pickup', 'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'sessions' => 'integer',
            'has_pickup' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
