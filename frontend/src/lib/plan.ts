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
