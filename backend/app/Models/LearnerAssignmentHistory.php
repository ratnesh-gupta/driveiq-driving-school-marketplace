<?php

namespace App\Models;

use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerAssignmentHistory extends Model
{
    use BelongsToSchool;

    protected $table = 'learner_assignment_history';

    protected $fillable = [
        'learner_id', 'school_id', 'instructor_id', 'vehicle_id',
        'assigned_by', 'action', 'notes',
    ];

    public function learner(): BelongsTo
    {
        return $this->belongsTo(Learner::class);
    }
}
