/**
 * Plan gating on the client (DIQ-802). The server is the authority: any 402
 * plan_required response is broadcast as a window event so one listener can
 * explain it; pages use useEntitlements() to show locks up front.
 */

export type PlanFeature =
  | "learners"
  | "documents"
  | "instructors"
  | "schedules"
  | "vehicles"
  | "payments"
  | "analytics_advanced";

export type PlanRequired = {
  code: "plan_required";
  message: string;
  feature: PlanFeature;
  requiredPlan: string | null;
};

export const PLAN_REQUIRED_EVENT = "driveiq:plan-required";

/** Call with a 402 body; ignores anything that is not plan_required. */
export function announcePlanRequired(body: unknown): void {
  if (body && typeof body === "object" && (body as PlanRequired).code === "plan_required") {
    window.dispatchEvent(new CustomEvent<PlanRequired>(PLAN_REQUIRED_EVENT, { detail: body as PlanRequired }));
  }
}

export const PLAN_LABEL: Record<string, string> = {
  basic: "Basic",
  featured: "Featured",
  premium: "Premium",
  enterprise: "Enterprise",
};

/** Which dashboard routes belong to which gated feature. */
export const ROUTE_FEATURE: Record<string, PlanFeature> = {
  "/dashboard/learners": "learners",
  "/dashboard/instructors": "instructors",
  "/dashboard/schedules": "schedules",
  "/dashboard/vehicles": "vehicles",
  "/dashboard/payments": "payments",
};

export const FEATURE_PLAN: Record<PlanFeature, string> = {
  learners: "featured",
  documents: "featured",
  instructors: "premium",
  schedules: "premium",
  vehicles: "premium",
  payments: "premium",
  analytics_advanced: "premium",
};

/** What each plan unlocks, for the comparison table (mirrors backend config/plans.php). */
export const PLAN_MATRIX: { code: string; highlights: string[] }[] = [
  { code: "basic", highlights: ["Marketplace listing", "Leads, reminders & reply-time tracking", "Reviews, messages, team"] },
  { code: "featured", highlights: ["Everything in Basic", "Learners, documents & lead conversion", "Ranking boost + highlighted card"] },
  { code: "premium", highlights: ["Everything in Featured", "Instructors, schedules, vehicles, payments", "Advanced analytics", "Top placement (Sponsored) + homepage"] },
  { code: "enterprise", highlights: ["Everything in Premium", "Multi-branch & API access (coming)", "Priority support"] },
];

export function formatInr(amount: number): string {
  return new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 0 }).format(amount);
}

export function daysUntil(iso: string | null | undefined): number | null {
  if (!iso) return null;
  return Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000));
}
