<?php

namespace App\Services;

use App\Models\Inquiry;
use Illuminate\Support\Facades\DB;

/**
 * How quickly schools answer leads (DIQ-703), over leads created in the last
 * $days days. Unanswered leads count against the "within" rates.
 */
class LeadResponseStats
{
    public const WINDOW_DAYS = 90;

    /** Answered leads needed in the window before a school gets a public badge. */
    public const BADGE_MIN_LEADS = 5;

    /**
     * @return array{leads: int, responded: int, awaitingReply: int, medianSeconds: ?int,
     *               averageSeconds: ?int, within1hRate: float, within24hRate: float, windowDays: int}
     */
    public function summary(?int $schoolId = null, int $days = self::WINDOW_DAYS): array
    {
        $row = Inquiry::withoutGlobalScope('school')
            ->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('COUNT(*) AS leads')
            ->selectRaw('COUNT(first_responded_at) AS responded')
            ->selectRaw('COUNT(*) FILTER (WHERE first_responded_at IS NULL AND status = ?) AS awaiting', ['pending'])
            ->selectRaw('COUNT(*) FILTER (WHERE response_seconds <= 3600) AS within_1h')
            ->selectRaw('COUNT(*) FILTER (WHERE response_seconds <= 86400) AS within_24h')
            ->selectRaw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY response_seconds) AS median_s')
            ->selectRaw('AVG(response_seconds) AS avg_s')
            ->toBase()
            ->first();

        $leads = (int) $row->leads;

        return [
            'windowDays' => $days,
            'leads' => $leads,
            'responded' => (int) $row->responded,
            'awaitingReply' => (int) $row->awaiting,
            'medianSeconds' => $row->median_s === null ? null : (int) round((float) $row->median_s),
            'averageSeconds' => $row->avg_s === null ? null : (int) round((float) $row->avg_s),
            'within1hRate' => $leads > 0 ? round($row->within_1h / $leads, 4) : 0.0,
            'within24hRate' => $leads > 0 ? round($row->within_24h / $leads, 4) : 0.0,
        ];
    }

    /**
     * Recompute every school's public median reply time (minutes, rounded up),
     * or null below BADGE_MIN_LEADS answered leads in the window (DIQ-708).
     */
    public function refreshSchoolBadges(int $days = self::WINDOW_DAYS): int
    {
        return DB::update(
            'UPDATE schools s SET typical_response_minutes = (
                SELECT CASE WHEN COUNT(i.response_seconds) >= ?
                    THEN CEIL(PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY i.response_seconds) / 60.0)::int END
                FROM inquiries i
                WHERE i.school_id = s.id AND i.created_at >= ?
            )',
            [self::BADGE_MIN_LEADS, now()->subDays($days)]
        );
    }

    /**
     * Per-school medians for the admin view, slowest first.
     *
     * @return list<array{schoolId: int, schoolName: string, leads: int, medianSeconds: ?int, awaitingReply: int}>
     */
    public function bySchool(int $days = self::WINDOW_DAYS, int $limit = 20): array
    {
        return DB::table('inquiries')
            ->join('schools', 'schools.id', '=', 'inquiries.school_id')
            ->where('inquiries.created_at', '>=', now()->subDays($days))
            ->groupBy('schools.id', 'schools.name')
            ->select('schools.id', 'schools.name')
            ->selectRaw('COUNT(*) AS leads')
            ->selectRaw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY inquiries.response_seconds) AS median_s')
            ->selectRaw('COUNT(*) FILTER (WHERE inquiries.first_responded_at IS NULL AND inquiries.status = ?) AS awaiting', ['pending'])
            ->orderByRaw('median_s DESC NULLS FIRST')
            ->orderBy('schools.id')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'schoolId' => (int) $r->id,
                'schoolName' => $r->name,
                'leads' => (int) $r->leads,
                'medianSeconds' => $r->median_s === null ? null : (int) round((float) $r->median_s),
                'awaitingReply' => (int) $r->awaiting,
            ])
            ->all();
    }
}
