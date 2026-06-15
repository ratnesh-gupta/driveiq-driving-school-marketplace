import { useQueries } from "@tanstack/react-query";
import { getGetSchoolQueryOptions } from "@/api-client";
import type { School } from "@/api-client/generated/api.schemas";
import { useComparisonStore } from "../stores/comparison-store";

export function useComparisonSchools(idsOverride?: number[]) {
  const storeIds = useComparisonStore((s) => s.schoolIds);
  const ids = idsOverride ?? storeIds;

  const results = useQueries({
    queries: ids.map((id) => ({
      ...getGetSchoolQueryOptions(id),
      enabled: !!id,
    })),
  });

  const schools = results
    .map((r) => r.data as School | undefined)
    .filter((s): s is School => !!s);

  const isLoading = results.some((r) => r.isLoading);

  return { schools, isLoading };
}
