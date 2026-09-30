import { Link } from "wouter";
import { Lock } from "lucide-react";
import { useEntitlements } from "@/hooks/use-entitlements";
import { FEATURE_PLAN, PLAN_LABEL, type PlanFeature } from "@/lib/plan";

/**
 * Shown at the top of a module page when the school's plan does not include
 * it: the data stays visible, changes need an upgrade (DIQ-802).
 */
export function PlanGateBanner({ feature, readsLocked = false }: { feature: PlanFeature; readsLocked?: boolean }) {
  const { has, isLoading } = useEntitlements();
  if (isLoading || has(feature)) return null;

  const plan = PLAN_LABEL[FEATURE_PLAN[feature]] ?? "a paid";
  return (
    <div
      className="mb-6 rounded-xl border border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 p-4 flex flex-wrap items-center gap-3"
      data-testid={`plan-gate-${feature}`}
    >
      <Lock className="h-4 w-4 text-amber-700 dark:text-amber-400 shrink-0" />
      <p className="text-sm flex-1 min-w-[200px]">
        {readsLocked
          ? `This is part of the ${plan} plan.`
          : `Read-only on your plan. Your existing records stay visible; upgrade to ${plan} to add or change them.`}
      </p>
      <Link href="/dashboard/billing" className="text-sm font-medium text-primary hover:underline">
        See plans
      </Link>
    </div>
  );
}
