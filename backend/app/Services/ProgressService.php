<?php

namespace App\Services;

use App\Models\TrainingProgress;
use Illuminate\Support\Collection;

class ProgressService
{
    /**
     * Ensure default skill rows exist for a learner; return current snapshot.
     */
    public function snapshot(int $learnerId, int $schoolId): array
    {
        foreach (TrainingProgress::SKILLS as $skill) {
            TrainingProgress::withoutGlobalScope('school')->firstOrCreate(
                ['learner_id' => $learnerId, 'skill_name' => $skill],
                ['school_id' => $schoolId, 'percentage' => 0]
            );
        }

        $rows = TrainingProgress::withoutGlobalScope('school')
            ->where('learner_id', $learnerId)
            ->orderBy('skill_name')
            ->get();

        $overall = $rows->count() > 0
            ? (int) round($rows->avg('percentage'))
            : 0;

        return [
            'skills' => $rows->map(fn (TrainingProgress $p) => [
                'skillName' => $p->skill_name,
                'percentage' => (int) $p->percentage,
                'notes' => $p->notes,
                'updatedAt' => $p->updated_at?->toISOString(),
                'sessionId' => $p->session_id,
            ])->values()->all(),
            'overallCompletion' => $overall,
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
