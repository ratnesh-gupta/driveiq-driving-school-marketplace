<?php

namespace App\Http\Middleware;

use App\Support\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plan gating (DIQ-802). Looks the matched route up in config('plans.routes');
 * for a school without that feature, writes get 402 plan_required while reads
 * keep working (read-only), except for features in plans.gate_reads.
 * Platform admins are never gated.
 */
class EnforcePlanFeatures
{
    public function __construct(private Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        $feature = config('plans.routes')[$request->route()?->uri()] ?? null;
        $user = $request->user();

        if (! $feature || ! config('plans.enforce', true) || ! $user || $user->isAdmin() || ! $user->school_id) {
            return $next($request);
        }

        $isRead = $request->isMethodSafe();
        if ($isRead && ! in_array($feature, config('plans.gate_reads', []), true)) {
            return $next($request);
        }

        if ($this->entitlements->allows((int) $user->school_id, $feature)) {
            return $next($request);
        }

        $required = $this->entitlements->cheapestPlanWith($feature);

        return response()->json([
            'message' => $isRead
                ? 'This is part of the '.ucfirst((string) $required).' plan.'
                : 'Your plan is read-only for this. Upgrade to '.ucfirst((string) $required).' to make changes.',
            'code' => 'plan_required',
            'feature' => $feature,
            'requiredPlan' => $required,
        ], 402);
    }
}
