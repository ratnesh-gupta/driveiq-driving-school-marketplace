<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(
        private readonly StatsService $statsService,
    ) {}

    public function overview(): JsonResponse
    {
        return response()->json($this->statsService->overview());
    }

    public function school(int $schoolId): JsonResponse
    {
        if ($schoolId <= 0) {
            return response()->json(['message' => 'Invalid schoolId'], 400);
        }

        return response()->json($this->statsService->forSchool($schoolId));
    }
}
