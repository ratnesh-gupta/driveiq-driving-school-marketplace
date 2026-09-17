<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Support\Collection;

class SubscriptionService
{
    public function listPlans(): Collection
    {
        return Plan::where('active', true)->orderBy('sort_order')->get();
    }

    public function activeForSchool(int $schoolId): ?Subscription
    {
        $sub = Subscription::withoutGlobalScope('school')
            ->with('plan')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();

        return $sub;
    }

    public function planCodeForSchool(int $schoolId): string
    {
        $sub = $this->activeForSchool($schoolId);

        return $sub?->plan?->code ?? 'basic';
    }

    public function rankingBoostForSchool(int $schoolId): float
    {
        $sub = $this->activeForSchool($schoolId);

        return $sub?->plan?->rankingBoostScore() ?? 0.0;
    }

    public function isSponsored(int $schoolId): bool
    {
        $sub = $this->activeForSchool($schoolId);

        return (bool) ($sub?->plan?->is_sponsored);
    }

    /**
     * Assign or replace active subscription (admin).
     */
    public function assign(int $schoolId, string $planCode, int $months = 1, ?int $createdBy = null, bool $autoRenew = false): Subscription
    {
        $plan = Plan::where('code', $planCode)->where('active', true)->firstOrFail();

        // Expire previous active subs
        Subscription::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $starts = now();
        $expires = $plan->code === 'basic' ? null : $starts->copy()->addMonths($months);

        // Sync school premium_verified for premium+ tiers
        if (in_array($plan->code, ['premium', 'enterprise'], true)) {
            School::where('id', $schoolId)->update([
                'premium_verified' => true,
                'verified' => true,
            ]);
        }

        return Subscription::withoutGlobalScope('school')->create([
            'school_id' => $schoolId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => $starts,
            'expires_at' => $expires,
            'auto_renew' => $autoRenew,
            'created_by' => $createdBy,
        ]);
    }

    public function cancel(int $schoolId): ?Subscription
    {
        $sub = $this->activeForSchool($schoolId);
        if (! $sub) {
            return null;
        }

        $sub->update(['status' => 'cancelled', 'auto_renew' => false]);

        return $sub->fresh('plan');
    }

    public function adminOverview(): array
    {
        $active = Subscription::withoutGlobalScope('school')
            ->with('plan')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        $byTier = $active->groupBy(fn (Subscription $s) => $s->plan?->code ?? 'unknown')
            ->map->count();

        $mrr = $active->sum(fn (Subscription $s) => (int) ($s->plan?->price_monthly ?? 0));

        $expiringSoon = Subscription::withoutGlobalScope('school')
            ->with(['plan', 'school:id,name,slug'])
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(14)])
            ->orderBy('expires_at')
            ->limit(20)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'schoolId' => $s->school_id,
                'schoolName' => $s->school?->name,
                'planCode' => $s->plan?->code,
                'expiresAt' => $s->expires_at?->toISOString(),
            ]);

        return [
            'activeSubscriptions' => $active->count(),
            'byTier' => $byTier,
            'mrr' => $mrr,
            'expiringSoon' => $expiringSoon,
        ];
    }
}
