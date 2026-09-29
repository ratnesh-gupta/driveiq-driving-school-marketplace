<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(
        private readonly StatsService $statsService,
    ) {}

    public function overview(): JsonResponse
    {
        return response()->json($this->statsService->overview());
    }

    public function school(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        return response()->json($this->statsService->forSchool($schoolId));
    }
}
