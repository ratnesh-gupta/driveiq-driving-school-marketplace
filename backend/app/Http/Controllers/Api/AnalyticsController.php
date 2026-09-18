<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function school(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authSchool($request, $schoolId)) {
            return $deny;
        }

        return response()->json($this->analytics->forSchool($schoolId));
    }

    public function instructors(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authSchool($request, $schoolId)) {
            return $deny;
        }

        $instructorId = $request->query('instructorId');

        return response()->json([
            'schoolId' => $schoolId,
            'instructors' => $this->analytics->instructorPerformance(
                $schoolId,
                $instructorId ? (int) $instructorId : null
            ),
        ]);
    }

    public function platform(Request $request): JsonResponse
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->analytics->platform());
    }

    private function authSchool(Request $request, int $schoolId): ?JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $user = $request->user();
        if ($user->isAdmin() || ($user->isSchool() && (int) $user->school_id === $schoolId)) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
