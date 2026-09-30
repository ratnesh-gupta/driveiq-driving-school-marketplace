<?php

namespace App\Services;

use App\Models\TrainingProgress;
use Illuminate\Support\Collection;

class ProgressService
{
    /**
     * Current progress for every skill. Read-only: skills without a stored
     * row are reported at 0% instead of being created on each view.
     */
    public function snapshot(int $learnerId, int $schoolId): array
    {
        $stored = TrainingProgress::withoutGlobalScope('school')
            ->where('learner_id', $learnerId)
            ->where('school_id', $schoolId)
            ->get()
            ->keyBy('skill_name');

        $skills = collect(TrainingProgress::SKILLS)->sort()->values()->map(function (string $skill) use ($stored) {
            $p = $stored->get($skill);

            return [
                'skillName' => $skill,
                'percentage' => (int) ($p->percentage ?? 0),
                'notes' => $p?->notes,
                'updatedAt' => $p?->updated_at?->toISOString(),
                'sessionId' => $p?->session_id,
            ];
        });

        return [
            'skills' => $skills->all(),
            'overallCompletion' => (int) round($skills->avg('percentage')),
        ];
    }

    public function updateSkill(
        int $learnerId,
        int $schoolId,
        string $skillName,
        int $percentage,
        ?int $instructorId = null,
        ?int $sessionId = null,
        ?string $notes = null
    ): TrainingProgress {
        if (! in_array($skillName, TrainingProgress::SKILLS, true)) {
            throw new \InvalidArgumentException("Unknown skill: {$skillName}");
        }

        $percentage = max(0, min(100, $percentage));

        $row = TrainingProgress::withoutGlobalScope('school')->updateOrCreate(
            ['learner_id' => $learnerId, 'skill_name' => $skillName],
            [
                'school_id' => $schoolId,
                'percentage' => $percentage,
                'updated_by_instructor_id' => $instructorId,
                'session_id' => $sessionId,
                'notes' => $notes,
            ]
        );

        return $row;
    }

    public function skillList(): Collection
    {
        return collect(TrainingProgress::SKILLS);
    }
}
