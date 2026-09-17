<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\School;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function plans(): JsonResponse
    {
        $plans = $this->subscriptions->listPlans()->map(fn ($p) => [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'priceMonthly' => $p->price_monthly,
            'features' => $p->features,
            'rankingBoost' => $p->ranking_boost,
            'isSponsored' => $p->is_sponsored,
            'homepageFeatured' => $p->homepage_featured,
        ]);

        return response()->json($plans);
    }

    public function showForSchool(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authorizeSchool($request, $schoolId)) {
            return $deny;
        }

        $sub = $this->subscriptions->activeForSchool($schoolId);

        if (! $sub) {
            return response()->json([
                'schoolId' => $schoolId,
                'planCode' => 'basic',
                'status' => 'none',
                'isSponsored' => false,
                'rankingBoost' => 0,
            ]);
        }

        return response()->json($this->serialize($sub));
    }

    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schoolId' => ['required', 'integer', 'exists:schools,id'],
            'planCode' => ['required', 'string', 'in:basic,featured,premium,enterprise'],
            'months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'autoRenew' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $sub = $this->subscriptions->assign(
            (int) $data['schoolId'],
            $data['planCode'],
            (int) ($data['months'] ?? 1),
            $request->user()->id,
            (bool) ($data['autoRenew'] ?? false),
        );

        if (! empty($data['notes'])) {
            $sub->update(['notes' => $data['notes']]);
        }

        $sub->load('plan');

        AuditLog::log('assign_subscription', 'Subscription', $sub->id, [], [
            'school_id' => $sub->school_id,
            'plan' => $data['planCode'],
        ]);

        return response()->json($this->serialize($sub), 201);
    }

    public function cancel(Request $request, int $schoolId): JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $sub = $this->subscriptions->cancel($schoolId);

        if (! $sub) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        AuditLog::log('cancel_subscription', 'Subscription', $sub->id, ['status' => 'active'], ['status' => 'cancelled']);

        return response()->json($this->serialize($sub));
    }

    public function overview(): JsonResponse
    {
        return response()->json($this->subscriptions->adminOverview());
    }

    private function serialize($sub): array
    {
        return [
            'id' => $sub->id,
            'schoolId' => $sub->school_id,
            'planCode' => $sub->plan?->code,
            'planName' => $sub->plan?->name,
            'priceMonthly' => $sub->plan?->price_monthly,
            'status' => $sub->status,
            'startsAt' => $sub->starts_at?->toISOString(),
            'expiresAt' => $sub->expires_at?->toISOString(),
            'autoRenew' => (bool) $sub->auto_renew,
            'isSponsored' => (bool) ($sub->plan?->is_sponsored),
            'rankingBoost' => (int) ($sub->plan?->ranking_boost ?? 0),
            'features' => $sub->plan?->features ?? [],
        ];
    }

    private function authorizeSchool(Request $request, int $schoolId): ?JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $user = $request->user();
        if ($user->isAdmin()) {
            return null;
        }

        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }
}
