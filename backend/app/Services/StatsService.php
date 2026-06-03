<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Locality;
use App\Models\Review;
use App\Models\School;
use Illuminate\Support\Carbon;

class StatsService
{
    public function overview(): array
    {
        $schoolStats = School::query()
            ->selectRaw('count(*) as total_schools')
            ->selectRaw('sum(case when verified then 1 else 0 end) as verified_schools')
            ->selectRaw('avg(rating) as avg_rating')
            ->first();

        return [
            'totalSchools' => (int) ($schoolStats->total_schools ?? 0),
            'verifiedSchools' => (int) ($schoolStats->verified_schools ?? 0),
            'avgRating' => round((float) ($schoolStats->avg_rating ?? 0), 2),
            'totalLocalities' => Locality::count(),
            'totalReviews' => Review::count(),
            'totalInquiries' => Inquiry::count(),
        ];
    }

    public function forSchool(int $schoolId): array
    {
        $totalInquiries = Inquiry::where('school_id', $schoolId)->count();
        $pendingInquiries = Inquiry::where('school_id', $schoolId)->where('status', 'pending')->count();
        $thisMonthInquiries = Inquiry::where('school_id', $schoolId)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        $totalReviews = Review::where('school_id', $schoolId)->count();
        $avgRating = (float) (Review::where('school_id', $schoolId)->avg('rating') ?? 0);

        return [
            'schoolId' => $schoolId,
            'totalInquiries' => $totalInquiries,
            'pendingInquiries' => $pendingInquiries,
            'thisMonthInquiries' => $thisMonthInquiries,
            'totalReviews' => $totalReviews,
            'avgRating' => round($avgRating, 2),
        ];
    }
}
