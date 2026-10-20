<?php

namespace App\Support;

use App\Models\Subscription;

/**
 * What a school may use, from its plan (DIQ-802, config/plans.php).
 *
 * Resolution: features of the latest active, unexpired paid subscription
 * (Basic when none), plus the trial plan's features while an unexpired
 * trial exists. A trial adds features only; paid visibility comes from
 * status = active subscriptions (SchoolService::withPlanMeta).
 */
class Entitlements
{
    public const ALL_FEATURES = ['learners', 'documents', 'instructors', 'schedules', 'vehicles', 'payments', 'analytics_advanced'];

    /**
     * @return array{plan: string, planExpiresAt: ?string, trial: array{active: bool, endsAt: ?string, plan: string},
     *               features: list<string>, lockedFeatures: list<string>, enforced: bool}
     */
    public function forSchool(int $schoolId): array
    {
        $now = now();
        $current = fn ($q) => $q->where('school_id', $schoolId)
            ->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', $now));

        $paid = Subscription::withoutGlobalScope('school')->with('plan:id,code')
            ->where('status', 'active')->tap($current)->orderByDesc('id')->first();
        $trial = Subscription::withoutGlobalScope('school')
            ->where('status', 'trial')->tap($current)->orderByDesc('expires_at')->first();

        $plan = $paid?->plan?->code ?? 'basic';
        $trialPlan = config('plans.trial_plan', 'premium');

        $features = $this->featuresOf($plan);
        if ($trial) {
            $features = array_values(array_unique([...$features, ...$this->featuresOf($trialPlan)]));
        }

        return [
            'plan' => $plan,
            'planExpiresAt' => $paid?->expires_at?->toISOString(),
            'trial' => [
                'active' => (bool) $trial,
                'endsAt' => $trial?->expires_at?->toISOString(),
                'plan' => $trialPlan,
            ],
            'features' => $features,
            'lockedFeatures' => array_values(array_diff(self::ALL_FEATURES, $features)),
            'enforced' => (bool) config('plans.enforce', true),
        ];
    }

    public function allows(int $schoolId, string $feature): bool
    {
        return ! config('plans.enforce', true) || in_array($feature, $this->forSchool($schoolId)['features'], true);
    }

    /** The cheapest plan that includes a feature. */
    public function cheapestPlanWith(string $feature): ?string
    {
        foreach (config('plans.upgrade_order', []) as $code) {
            if (in_array($feature, $this->featuresOf($code), true)) {
                return $code;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function featuresOf(string $planCode): array
    {
        return config("plans.features.{$planCode}", []);
    }
}
