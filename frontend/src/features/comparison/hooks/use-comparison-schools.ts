import { useQuery } from "@tanstack/react-query";
import { getGetSchoolQueryOptions } from "@/api-client";
import type { School } from "@/api-client/generated/api.schemas";
import { compareSchools, type ComparisonBadges, type CompareResponse } from "@/lib/ops-api";
import { useComparisonStore } from "../stores/comparison-store";

export type ComparedSchool = CompareResponse<School>["schools"][number];

const NO_BADGES: ComparisonBadges = {
  bestRated: null,
  mostAffordable: null,
  bestValue: null,
  mostReviewed: null,
  womenFriendly: [],
};

/**
 * Schools being compared, with package/review summaries and insight badges,
 * from one GET /api/schools/compare request (DIQ-505). With a single school
 * selected (the compare bar), it falls back to that school's own endpoint.
 */
export function useComparisonSchools(idsOverride?: number[]) {
  const storeIds = useComparisonStore((s) => s.schoolIds);
  const ids = (idsOverride ?? storeIds).slice(0, 4);

  const compare = useQuery({
    queryKey: ["schools", "compare", ids],
    queryFn: () => compareSchools<School>(ids),
    enabled: ids.length >= 2,
  });

  const single = useQuery({
    ...getGetSchoolQueryOptions(ids[0] ?? 0),
    enabled: ids.length === 1,
  });

  if (ids.length === 1) {
    const school = single.data as School | undefined;
    return {
      schools: school ? [school as ComparedSchool] : [],
      badges: NO_BADGES,
      isLoading: single.isLoading,
    };
  }

  return {
    schools: compare.data?.schools ?? [],
    badges: compare.data?.badges ?? NO_BADGES,
    isLoading: ids.length >= 2 && compare.isLoading,
  };
}
