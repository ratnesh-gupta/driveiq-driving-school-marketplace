import { Link } from "wouter";
import { Sparkles, Lock } from "lucide-react";
import { useEntitlements } from "@/hooks/use-entitlements";
import { PLAN_LABEL, daysUntil } from "@/lib/plan";

/** Trial countdown, or a note that locked modules are read-only (DIQ-804). */
export function TrialBanner() {
  const { entitlements } = useEntitlements();
  if (!entitlements || !entitlements.enforced) return null;

  if (entitlements.trial.active && entitlements.plan === "basic") {
    const days = daysUntil(entitlements.trial.endsAt) ?? 0;
    return (
      <div className="mb-6 rounded-xl border border-primary/30 bg-primary/5 p-3 flex flex-wrap items-center gap-2 text-sm" data-testid="trial-banner">
        <Sparkles className="h-4 w-4 text-primary" />
        <span className="flex-1 min-w-[200px]">
          {PLAN_LABEL[entitlements.trial.plan] ?? "Premium"} trial: <strong>{days} day{days === 1 ? "" : "s"} left</strong>. After that, modules not on your plan become read-only (nothing is deleted).
        </span>
        <Link href="/dashboard/billing" className="font-medium text-primary hover:underline">Choose a plan</Link>
      </div>
    );
  }

  if (!entitlements.trial.active && entitlements.lockedFeatures.length > 0 && entitlements.plan === "basic") {
    return (
      <div className="mb-6 rounded-xl border border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40 p-3 flex flex-wrap items-center gap-2 text-sm" data-testid="trial-ended-banner">
        <Lock className="h-4 w-4 text-amber-700 dark:text-amber-400" />
        <span className="flex-1 min-w-[200px]">Your trial has ended. Learners, instructors and other operations modules are read-only on the Basic plan.</span>
        <Link href="/dashboard/billing" className="font-medium text-primary hover:underline">See plans</Link>
      </div>
    );
  }

  return null;
}
