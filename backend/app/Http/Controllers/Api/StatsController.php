<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Locality;
use App\Models\Review;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class StatsController extends Controller
{
    public function overview(): JsonResponse
    {
        $schoolStats = School::query()->selectRaw('count(*) as total_schools')
            ->selectRaw('sum(case when verified then 1 else 0 end) as verified_schools')
            ->selectRaw('avg(rating) as avg_rating')
            ->first();

        return response()->json([
            'totalSchools' => (int) ($schoolStats->total_schools ?? 0),
            'totalLocalities' => (int) Locality::query()->count(),
            'totalReviews' => (int) Review::query()->count(),
            'totalInquiries' => (int) Inquiry::query()->count(),
            'verifiedSchools' => (int) ($schoolStats->verified_schools ?? 0),
            'avgRating' => (float) ($schoolStats->avg_rating ?? 0),
        ]);
    }

    public function school(int $schoolId): JsonResponse
    {
        if ($schoolId <= 0) {
            return response()->json(['error' => 'Invalid schoolId'], 400);
        }

        $inquiries = Inquiry::query()->where('school_id', $schoolId);
        $reviews = Review::query()->where('school_id', $schoolId);

        $totalInquiries = (clone $inquiries)->count();
        $pendingInquiries = (clone $inquiries)->where('status', 'pending')->count();
        $thisMonthInquiries = (clone $inquiries)->where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        $totalReviews = (clone $reviews)->count();
        $avgRating = (float) ((clone $reviews)->avg('rating') ?? 0);

        return response()->json([
            'schoolId' => $schoolId,
            'totalInquiries' => $totalInquiries,
            'pendingInquiries' => $pendingInquiries,
            'thisMonthInquiries' => $thisMonthInquiries,
            'totalReviews' => $totalReviews,
            'avgRating' => $avgRating,
        ]);
    }
}
