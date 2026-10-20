import { useQuery } from "@tanstack/react-query";
import { useSchoolId } from "@/hooks/use-school-id";
import { fetchEntitlements } from "@/lib/ops-api";
import type { PlanFeature } from "@/lib/plan";

/** The signed-in school's plan, trial and unlocked features (DIQ-802). */
export function useEntitlements() {
  const schoolId = useSchoolId();
  const query = useQuery({
    queryKey: ["entitlements", schoolId],
    queryFn: () => fetchEntitlements(schoolId!),
    enabled: !!schoolId,
    staleTime: 60_000,
  });

  const has = (feature: PlanFeature) =>
    !query.data || !query.data.enforced || query.data.features.includes(feature);

  return { ...query, entitlements: query.data, has };
}
