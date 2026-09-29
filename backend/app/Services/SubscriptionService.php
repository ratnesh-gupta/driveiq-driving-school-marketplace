<?php

namespace App\Services;

use App\Models\FeaturedPlacement;
use App\Models\MarketplaceSetting;
use App\Models\Plan;
use App\Models\PlatformInvoice;
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

        // Paying never changes verification flags: "Verified" is earned through
        // the admin verification workflow (DIQ-506), not bought (DIQ-801).

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

    /**
     * Paid renewal: the same plan still running is extended from its current
     * end date; anything else starts the new plan now (DIQ-803).
     */
    public function extendOrAssign(int $schoolId, string $planCode, int $months, ?int $createdBy = null): Subscription
    {
        $current = $this->activeForSchool($schoolId);

        if ($current && $current->plan?->code === $planCode && $current->expires_at) {
            $current->update(['expires_at' => $current->expires_at->copy()->addMonths($months)]);

            return $current->fresh('plan');
        }

        return $this->assign($schoolId, $planCode, $months, $createdBy);
    }

    /**
     * Start a school's feature trial (DIQ-802), once: a school that already
     * had a trial or a paid plan does not get another.
     */
    public function startTrial(int $schoolId): ?Subscription
    {
        $hadAny = Subscription::withoutGlobalScope('school')->where('school_id', $schoolId)->exists();
        $plan = Plan::where('code', config('plans.trial_plan', 'premium'))->first();

        if ($hadAny || ! $plan || config('plans.trial_days', 30) <= 0) {
            return null;
        }

        return Subscription::withoutGlobalScope('school')->create([
            'school_id' => $schoolId,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'starts_at' => now(),
            'expires_at' => now()->addDays((int) config('plans.trial_days', 30)),
            'notes' => 'Feature trial',
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

    /**
     * Admin monetization metrics (DIQ-806). "Paid" means a plan with a price.
     * Churn (30 days) = paid subscriptions that ended in the window with no
     * paid plan active now, divided by paid subscriptions live at its start.
     */
    public function adminOverview(): array
    {
        $now = now();
        $live = fn ($q) => $q->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now));
        $paid = fn ($q) => $q->whereHas('plan', fn ($p) => $p->where('price_monthly', '>', 0));

        $active = Subscription::withoutGlobalScope('school')->with('plan')
            ->where('status', 'active')->tap($live)->get();
        $activePaid = $active->filter(fn (Subscription $s) => (int) ($s->plan?->price_monthly ?? 0) > 0);

        $byTier = $active->groupBy(fn (Subscription $s) => $s->plan?->code ?? 'unknown')->map->count();
        $mrrByTier = $activePaid->groupBy(fn (Subscription $s) => $s->plan->code)
            ->map(fn ($subs) => $subs->sum(fn (Subscription $s) => (int) $s->plan->price_monthly));
        $mrr = (int) $mrrByTier->sum();

        $windowStart = $now->copy()->subDays(30);
        $paidAtStart = Subscription::withoutGlobalScope('school')->tap($paid)
            ->whereIn('status', ['active', 'cancelled', 'expired'])
            ->where('starts_at', '<=', $windowStart)
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $windowStart))
            ->count();
        $payingNow = $activePaid->pluck('school_id')->unique();
        $churned = Subscription::withoutGlobalScope('school')->tap($paid)
            ->where(fn ($q) => $q
                ->whereBetween('expires_at', [$windowStart, $now])
                ->orWhere(fn ($c) => $c->where('status', 'cancelled')->whereBetween('updated_at', [$windowStart, $now])))
            ->whereNotIn('school_id', $payingNow->all() ?: [0])
            ->distinct()
            ->count('school_id');

        $soon = fn (string $status, int $days) => Subscription::withoutGlobalScope('school')
            ->with(['plan', 'school:id,name,slug'])
            ->where('status', $status)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $now->copy()->addDays($days)])
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

        $slots = (int) MarketplaceSetting::get('sponsored_slots_per_page');
        $topPlans = config('geo.top_placement_plans', []);
        $sponsorSchools = $active->filter(fn (Subscription $s) => in_array($s->plan?->code, $topPlans, true))->pluck('school_id')
            ->merge(FeaturedPlacement::live()->where('placement', 'search_top')->pluck('school_id'))
            ->unique()->count();

        $pending = PlatformInvoice::where('status', 'issued');

        return [
            'activeSubscriptions' => $active->count(),
            'byTier' => $byTier,
            'activePaid' => $activePaid->count(),
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'mrrByTier' => $mrrByTier,
            'trials' => Subscription::withoutGlobalScope('school')->where('status', 'trial')->tap($live)->count(),
            'churn30d' => [
                'churned' => $churned,
                'paidAtStart' => $paidAtStart,
                'rate' => $paidAtStart > 0 ? round($churned / $paidAtStart, 4) : 0.0,
            ],
            'expiringSoon' => $soon('active', 14),
            'trialsEndingSoon' => $soon('trial', 7),
            'sponsoredSlots' => [
                'perPage' => $slots,
                'eligibleSchools' => $sponsorSchools,
                'utilization' => $slots > 0 ? round(min($sponsorSchools, $slots) / $slots, 4) : 0.0,
            ],
            'pendingInvoices' => [
                'count' => (clone $pending)->count(),
                'total' => (int) (clone $pending)->sum('total'),
            ],
        ];
    }
}
