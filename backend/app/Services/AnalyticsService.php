<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Inquiry;
use App\Models\Instructor;
use App\Models\Learner;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Subscription;
use App\Models\TrainingProgress;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function forSchool(int $schoolId): array
    {
        $now = now();
        $startThisMonth = $now->copy()->startOfMonth();
        $startLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endLastMonth = $now->copy()->subMonth()->endOfMonth();
        $weekStart = $now->copy()->startOfWeek();

        $inquiriesThisMonth = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('created_at', '>=', $startThisMonth)
            ->count();

        $inquiriesLastMonth = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->count();

        $totalInquiries = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->count();

        $converted = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('status', 'converted')
            ->count();

        $conversionRate = $totalInquiries > 0
            ? round($converted / $totalInquiries, 4)
            : 0.0;

        $activeLearners = Learner::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->count();

        $completedLearners = Learner::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('status', 'completed')
            ->count();

        $totalLearners = Learner::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->count();

        $completionRate = $totalLearners > 0
            ? round($completedLearners / $totalLearners, 4)
            : 0.0;

        $avgProgress = (float) (TrainingProgress::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->avg('percentage') ?? 0);

        $sessionsThisWeek = Schedule::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('session_date', '>=', $weekStart->toDateString())
            ->whereIn('status', ['scheduled', 'completed', 'rescheduled'])
            ->count();

        $sessionsCompleted = Schedule::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('status', 'completed')
            ->count();

        $instructorStats = $this->instructorBreakdown($schoolId);

        $trend = $this->inquiryTrend($schoolId, $now);

        return [
            'schoolId' => $schoolId,
            'leads' => [
                'thisMonth' => $inquiriesThisMonth,
                'lastMonth' => $inquiriesLastMonth,
                'total' => $totalInquiries,
                'converted' => $converted,
                'conversionRate' => $conversionRate,
                'trend' => $trend,
            ],
            'learners' => [
                'active' => $activeLearners,
                'completed' => $completedLearners,
                'total' => $totalLearners,
                'completionRate' => $completionRate,
                'avgSkillProgress' => round($avgProgress, 1),
            ],
            'sessions' => [
                'thisWeek' => $sessionsThisWeek,
                'completedTotal' => $sessionsCompleted,
            ],
            'instructors' => $instructorStats,
        ];
    }

    public function instructorPerformance(int $schoolId, ?int $instructorId = null): array
    {
        $query = Instructor::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('status', 'active');

        if ($instructorId) {
            $query->where('id', $instructorId);
        }

        $instructors = $query->get();

        return $instructors->map(function (Instructor $inst) use ($schoolId) {
            $sessionsCompleted = Schedule::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('instructor_id', $inst->id)
                ->where('status', 'completed')
                ->count();

            $sessionsTotal = Schedule::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('instructor_id', $inst->id)
                ->whereIn('status', ['scheduled', 'completed', 'rescheduled', 'cancelled'])
                ->count();

            $learnersAssigned = Learner::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('assigned_instructor_id', $inst->id)
                ->whereIn('status', ['active', 'completed'])
                ->count();

            $attendancePresent = Attendance::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('instructor_id', $inst->id)
                ->where('status', 'present')
                ->count();

            $attendanceTotal = Attendance::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('instructor_id', $inst->id)
                ->count();

            $attendanceRate = $attendanceTotal > 0
                ? round($attendancePresent / $attendanceTotal, 4)
                : null;

            $completedLearners = Learner::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('assigned_instructor_id', $inst->id)
                ->where('status', 'completed')
                ->count();

            $completionRate = $learnersAssigned > 0
                ? round($completedLearners / $learnersAssigned, 4)
                : 0.0;

            $weekStart = now()->startOfWeek()->toDateString();
            $weekSessions = Schedule::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('instructor_id', $inst->id)
                ->where('session_date', '>=', $weekStart)
                ->whereIn('status', ['scheduled', 'completed', 'rescheduled'])
                ->get(['start_time', 'end_time']);

            $hoursThisWeek = $weekSessions->sum(function ($s) {
                try {
                    $start = strtotime((string) $s->start_time);
                    $end = strtotime((string) $s->end_time);

                    return $end > $start ? ($end - $start) / 3600 : 0;
                } catch (\Throwable) {
                    return 1.0;
                }
            });

            return [
                'instructorId' => $inst->id,
                'name' => $inst->name,
                'sessionsCompleted' => $sessionsCompleted,
                'sessionsTotal' => $sessionsTotal,
                'learnersAssigned' => $learnersAssigned,
                'attendanceRate' => $attendanceRate,
                'completionRate' => $completionRate,
                'hoursThisWeek' => round($hoursThisWeek, 1),
                'totalLearnersTrained' => (int) ($inst->total_learners_trained ?? 0),
            ];
        })->values()->all();
    }

    public function platform(): array
    {
        $totalSchools = School::count();
        $verifiedSchools = School::where('verified', true)->count();

        $activeSubsQuery = Subscription::withoutGlobalScope('school')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        $subscribed = (clone $activeSubsQuery)->distinct()->count('school_id');

        $totalInquiries = Inquiry::withoutGlobalScope('school')->count();
        $converted = Inquiry::withoutGlobalScope('school')->where('status', 'converted')->count();
        $pending = Inquiry::withoutGlobalScope('school')->where('status', 'pending')->count();
        $contacted = Inquiry::withoutGlobalScope('school')->where('status', 'contacted')->count();

        $totalLearners = Learner::withoutGlobalScope('school')->count();
        $activeLearners = Learner::withoutGlobalScope('school')->where('status', 'active')->count();

        $mrrByPlan = Subscription::withoutGlobalScope('school')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'active')
            ->where(function ($q) {
                $q->whereNull('subscriptions.expires_at')
                    ->orWhere('subscriptions.expires_at', '>', now());
            })
            ->select(
                'plans.code as plan',
                'plans.name as plan_name',
                DB::raw('count(subscriptions.id) as schools'),
                DB::raw('coalesce(sum(plans.price_monthly), 0) as mrr')
            )
            ->groupBy('plans.code', 'plans.name')
            ->get()
            ->map(fn ($r) => [
                'plan' => $r->plan,
                'planName' => $r->plan_name,
                'schools' => (int) $r->schools,
                'mrr' => (float) $r->mrr,
            ])
            ->values()
            ->all();

        $totalMrr = collect($mrrByPlan)->sum('mrr');

        $topLocalities = Inquiry::withoutGlobalScope('school')
            ->join('schools', 'inquiries.school_id', '=', 'schools.id')
            ->leftJoin('localities', 'schools.locality_id', '=', 'localities.id')
            ->select(
                'localities.id as locality_id',
                'localities.name as locality_name',
                DB::raw('count(inquiries.id) as inquiry_count')
            )
            ->groupBy('localities.id', 'localities.name')
            ->orderByDesc('inquiry_count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'localityId' => $r->locality_id,
                'name' => $r->locality_name ?? 'Unknown',
                'inquiryCount' => (int) $r->inquiry_count,
            ])
            ->values()
            ->all();

        return [
            'schools' => [
                'total' => $totalSchools,
                'verified' => $verifiedSchools,
                'subscribed' => $subscribed,
            ],
            'funnel' => [
                'inquiries' => $totalInquiries,
                'pending' => $pending,
                'contacted' => $contacted,
                'converted' => $converted,
                'conversionRate' => $totalInquiries > 0 ? round($converted / $totalInquiries, 4) : 0.0,
            ],
            'learners' => [
                'total' => $totalLearners,
                'active' => $activeLearners,
            ],
            'revenue' => [
                'mrr' => (float) $totalMrr,
                'arr' => (float) ($totalMrr * 12),
                'byPlan' => $mrrByPlan,
            ],
            'topLocalities' => $topLocalities,
        ];
    }

    private function inquiryTrend(int $schoolId, $now): array
    {
        $since = $now->copy()->subMonths(5)->startOfMonth();

        if (DB::getDriverName() === 'pgsql') {
            return Inquiry::withoutGlobalScope('school')
                ->where('school_id', $schoolId)
                ->where('created_at', '>=', $since)
                ->selectRaw("to_char(created_at, 'YYYY-MM') as month, count(*) as count")
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($r) => ['month' => $r->month, 'count' => (int) $r->count])
                ->values()
                ->all();
        }

        return Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('created_at', '>=', $since)
            ->get()
            ->groupBy(fn ($i) => $i->created_at?->format('Y-m') ?? 'unknown')
            ->map(fn ($g, $m) => ['month' => $m, 'count' => $g->count()])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function instructorBreakdown(int $schoolId): array
    {
        $rows = $this->instructorPerformance($schoolId);

        usort($rows, fn ($a, $b) => ($b['sessionsCompleted'] ?? 0) <=> ($a['sessionsCompleted'] ?? 0));

        return [
            'count' => count($rows),
            'topPerformers' => array_slice($rows, 0, 5),
            'utilization' => array_map(fn ($r) => [
                'instructorId' => $r['instructorId'],
                'name' => $r['name'],
                'hoursThisWeek' => $r['hoursThisWeek'],
                'learnersAssigned' => $r['learnersAssigned'],
            ], $rows),
        ];
    }
}
