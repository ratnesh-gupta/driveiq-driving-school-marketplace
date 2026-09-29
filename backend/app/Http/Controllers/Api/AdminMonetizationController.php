<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FeaturedPlacement;
use App\Models\MarketplaceSetting;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** DIQ-806: platform admin monetization console (subscriptions, placements, slot settings). */
class AdminMonetizationController extends Controller
{
    public function subscriptions(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:active,trial,cancelled,expired'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = Subscription::withoutGlobalScope('school')
            ->with(['plan:id,code,name,price_monthly', 'school:id,name,slug'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['search'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';
                $q->whereHas('school', fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', [$like]));
            })
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (Subscription $s) => [
                'id' => $s->id,
                'schoolId' => $s->school_id,
                'schoolName' => $s->school?->name,
                'planCode' => $s->plan?->code,
                'priceMonthly' => $s->plan?->price_monthly,
                'status' => $s->status,
                'startsAt' => $s->starts_at?->toISOString(),
                'expiresAt' => $s->expires_at?->toISOString(),
                'current' => in_array($s->status, ['active', 'trial'], true) && (! $s->expires_at || $s->expires_at->isFuture()),
                'notes' => $s->notes,
            ]),
            'meta' => ['page' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function placements(): JsonResponse
    {
        return response()->json(
            FeaturedPlacement::with(['school:id,name', 'locality:id,name'])
                ->where('ends_at', '>', now()->subDays(30))
                ->orderByDesc('starts_at')
                ->get()
                ->map(fn (FeaturedPlacement $p) => $p->toApi())
        );
    }

    public function storePlacement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schoolId' => ['required', 'integer', 'exists:schools,id'],
            'placement' => ['required', Rule::in(FeaturedPlacement::PLACEMENTS)],
            'localityId' => ['required_if:placement,locality', 'nullable', 'integer', 'exists:localities,id'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $placement = FeaturedPlacement::create([
            'school_id' => $data['schoolId'],
            'placement' => $data['placement'],
            'locality_id' => $data['placement'] === 'locality' ? $data['localityId'] : null,
            'starts_at' => $data['startsAt'],
            'ends_at' => $data['endsAt'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        AuditLog::log('create_placement', 'FeaturedPlacement', $placement->id, [], $placement->only([
            'placement', 'locality_id', 'starts_at', 'ends_at',
        ]), $placement->school_id);

        return response()->json($placement->load('school:id,name', 'locality:id,name')->toApi(), 201);
    }

    /** End a campaign now (kept for the record). */
    public function endPlacement(Request $request, int $id): JsonResponse
    {
        $placement = FeaturedPlacement::findOrFail($id);
        $old = $placement->ends_at;
        if ($placement->ends_at->isFuture()) {
            $placement->update(['ends_at' => now()]);
        }

        AuditLog::log('end_placement', 'FeaturedPlacement', $placement->id, ['ends_at' => $old], ['ends_at' => $placement->ends_at], $placement->school_id);

        return response()->json($placement->load('school:id,name', 'locality:id,name')->toApi());
    }

    public function settings(): JsonResponse
    {
        return response()->json(MarketplaceSetting::allValues());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sponsored_slots_per_page' => ['sometimes', 'integer', 'min:0', 'max:10'],
            'homepage_slots' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ]);

        $old = MarketplaceSetting::allValues();
        foreach ($data as $key => $value) {
            MarketplaceSetting::set($key, (int) $value);
        }
        AuditLog::log('update', 'MarketplaceSetting', null, $old, MarketplaceSetting::allValues());

        return response()->json(MarketplaceSetting::allValues());
    }
}
