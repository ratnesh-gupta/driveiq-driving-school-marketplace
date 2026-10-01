<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** DIQ-1109: how schools and trainers are coming on board. Admin only. */
class AdminAcquisitionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $days = (int) ($request->validate(['days' => ['nullable', 'integer', 'in:7,30,90,365']])['days'] ?? 30);
        $since = now()->subDays($days);

        $prospects = DB::table('prospects');
        $stageCounts = (clone $prospects)->groupBy('stage')->selectRaw('stage, COUNT(*) AS n')->pluck('n', 'stage');
        $sourceCounts = (clone $prospects)->groupBy('source')->selectRaw('source, COUNT(*) AS n')->pluck('n', 'source');

        // Owned listings that came in during the period, by how they came.
        $newListings = DB::table('schools')
            ->where('listing_status', '!=', 'unclaimed')
            ->where(fn ($q) => $q->where('created_at', '>=', $since)->orWhere('claimed_at', '>=', $since))
            ->whereNotNull('user_id');

        $published = DB::table('schools')->where('published_at', '>=', $since);
        // From the owner taking it (registration or claim) to live.
        $hours = (clone $published)
            ->selectRaw('PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM published_at - COALESCE(claimed_at, created_at)) / 3600) AS median')
            ->value('median');

        $supply = DB::table('localities')
            ->leftJoin('schools', fn ($j) => $j->on('schools.locality_id', '=', 'localities.id')->where('schools.listing_status', 'published'))
            ->groupBy('localities.id', 'localities.name')
            ->orderBy('localities.name')
            ->selectRaw("localities.id, localities.name,
                COUNT(schools.id) FILTER (WHERE schools.listing_type = 'school') AS schools,
                COUNT(schools.id) FILTER (WHERE schools.listing_type = 'trainer') AS trainers")
            ->get()
            ->map(fn ($r) => ['localityId' => $r->id, 'locality' => $r->name, 'schools' => (int) $r->schools, 'trainers' => (int) $r->trainers]);

        return response()->json([
            'days' => $days,
            'prospects' => [
                'total' => (int) $stageCounts->sum(),
                'byStage' => collect(Prospect::STAGES)->mapWithKeys(fn ($s) => [$s => (int) ($stageCounts[$s] ?? 0)]),
                'bySource' => collect(Prospect::SOURCES)->mapWithKeys(fn ($s) => [$s => (int) ($sourceCounts[$s] ?? 0)]),
            ],
            'outreach' => [
                'sent' => DB::table('outreach_messages')->where('status', 'sent')->where('created_at', '>=', $since)->count(),
                'clicked' => DB::table('outreach_messages')->where('clicked_at', '>=', $since)->count(),
                'claimed' => DB::table('listing_claims')->whereNotNull('outreach_message_id')->where('claimed_at', '>=', $since)->count(),
                'unsubscribed' => DB::table('outreach_suppressions')->where('reason', 'unsubscribed')->where('created_at', '>=', $since)->count(),
            ],
            'ads' => [
                'leads' => DB::table('ad_leads')->where('is_test', false)->where('created_at', '>=', $since)->count(),
                'signedUp' => (clone $newListings)->where('source', 'ads')->count(),
            ],
            'listings' => [
                'joined' => (clone $newListings)->count(),
                'claimed' => DB::table('schools')->where('claimed_at', '>=', $since)->count(),
                'bySource' => (clone $newListings)->groupBy('source')->selectRaw('source, COUNT(*) AS n')->pluck('n', 'source')->map(fn ($n) => (int) $n),
                'published' => [
                    'schools' => (clone $published)->where('listing_type', 'school')->count(),
                    'trainers' => (clone $published)->where('listing_type', 'trainer')->count(),
                ],
                'waitingToPublish' => DB::table('schools')->where('listing_status', 'draft')->count(),
                'medianHoursToPublish' => $hours !== null ? round((float) $hours, 1) : null,
            ],
            'supply' => $supply,
        ]);
    }
}
