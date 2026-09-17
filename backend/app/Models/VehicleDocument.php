<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleDocument extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'vehicle_id', 'school_id', 'type', 'file_path', 'file_name', 'expiry_date', 'status',
    ];

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
