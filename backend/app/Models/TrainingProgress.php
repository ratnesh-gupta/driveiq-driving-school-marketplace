<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingProgress extends Model
{
    use BelongsToSchool;

    protected $table = 'training_progress';

    public const SKILLS = [
        'vehicle_controls',
        'parking',
        'reverse',
        'traffic_navigation',
        'night_driving',
        'highway_driving',
    ];

    protected $fillable = [
        'learner_id',
        'school_id',
        'skill_name',
        'percentage',
        'updated_by_instructor_id',
        'session_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'integer',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'updated_by_instructor_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'session_id');
    }
}
