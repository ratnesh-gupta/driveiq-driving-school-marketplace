<?php

namespace App\Support;

use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Schedule;
use App\Models\School;
use App\Models\SchoolAdmin;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single place for school-level authorization (PBAC).
 *
 * Each check returns null when allowed, or the JSON error response to return
 * (404 when the school does not exist, 403 otherwise), so controllers keep:
 *
 *     if ($deny = $this->access()->school($request, $schoolId)) {
 *         return $deny;
 *     }
 */
class SchoolAccess
{
    /**
     * Platform admin, or staff (role "school") of this school.
     *
     * @param  bool  $allowInstructor  also allow instructors of this school
     * @param  bool  $ownerOnly  staff must be the school's owner (not a manager)
     */
    public function school(
        Request $request,
        int $schoolId,
        bool $allowInstructor = false,
        bool $ownerOnly = false,
    ): ?JsonResponse {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $user = $request->user();

        if ($user->isAdmin()) {
            return null;
        }

        if ((int) $user->school_id !== $schoolId) {
            return $this->forbidden();
        }

        if ($user->isSchool()) {
            if ($ownerOnly && ! $this->isOwner($user, $schoolId)) {
                return $this->forbidden('Only the school owner can do this');
            }

            return null;
        }

        if ($allowInstructor && $user->isInstructor() && ! $ownerOnly) {
            return null;
        }

        return $this->forbidden();
    }

    /**
     * Access to one learner's record.
     *
     * @param  bool  $allowSelf  the learner themself
     * @param  bool  $allowInstructor  instructors of the learner's school
     */
    public function learner(
        Request $request,
        Learner $learner,
        bool $allowSelf = false,
        bool $allowInstructor = false,
    ): ?JsonResponse {
        $user = $request->user();

        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isSchool() && (int) $user->school_id === (int) $learner->school_id) {
            return null;
        }

        if ($allowSelf && $user->isLearner() && (int) $user->id === (int) $learner->user_id) {
            return null;
        }

        if ($allowInstructor && $user->isInstructor() && (int) $user->school_id === (int) $learner->school_id) {
            return null;
        }

        return $this->forbidden();
    }

    /** School staff, or the instructor the session is assigned to. */
    public function schedule(Request $request, Schedule $schedule): ?JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isSchool() && (int) $user->school_id === (int) $schedule->school_id) {
            return null;
        }

        if ($user->isInstructor() && (int) $schedule->instructor_id === $this->instructorIdFor($user)) {
            return null;
        }

        return $this->forbidden();
    }

    /** Active owner membership, or the school's legacy owner (schools.user_id). */
    public function isOwner(User $user, int $schoolId): bool
    {
        return SchoolAdmin::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->where('status', 'active')
            ->exists()
            || School::where('id', $schoolId)->where('user_id', $user->id)->exists();
    }

    public function instructorIdFor(User $user): ?int
    {
        $id = Instructor::withoutGlobalScope('school')->where('user_id', $user->id)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return response()->json(['message' => $message], 403);
    }
}
