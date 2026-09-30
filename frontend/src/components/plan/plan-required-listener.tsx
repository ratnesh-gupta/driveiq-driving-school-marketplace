import { useEffect } from "react";
import { useLocation } from "wouter";
import { toast } from "sonner";
import { useQueryClient } from "@tanstack/react-query";
import { useAuthStore } from "@/lib/store";
import { PLAN_REQUIRED_EVENT, type PlanRequired } from "@/lib/plan";

/** Explains any 402 plan_required response, with a way to upgrade for school staff. */
export function PlanRequiredListener() {
  const [, navigate] = useLocation();
  const role = useAuthStore((s) => s.userRole);
  const qc = useQueryClient();

  useEffect(() => {
    const onRequired = (e: Event) => {
      const detail = (e as CustomEvent<PlanRequired>).detail;
      // The plan may have changed (e.g. trial just ended): refresh what we show.
      void qc.invalidateQueries({ queryKey: ["entitlements"] });
      toast.error(detail.message, {
        id: `plan-${detail.feature}`,
        action: role === "school" ? { label: "See plans", onClick: () => navigate("/dashboard/billing") } : undefined,
      });
    };
    window.addEventListener(PLAN_REQUIRED_EVENT, onRequired);
    return () => window.removeEventListener(PLAN_REQUIRED_EVENT, onRequired);
  }, [navigate, role, qc]);

  return null;
}
