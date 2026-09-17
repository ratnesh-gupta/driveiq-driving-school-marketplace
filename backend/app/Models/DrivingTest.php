<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrivingTest extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'learner_id',
        'school_id',
        'test_date',
        'rto_name',
        'rto_location',
        'attempt_number',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'test_date' => 'date',
            'attempt_number' => 'integer',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
