<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'registration_number', 'type', 'transmission', 'fuel_type',
        'status', 'make_model', 'year', 'notes',
    ];

    protected function casts(): array
    {
        return ['year' => 'integer'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
