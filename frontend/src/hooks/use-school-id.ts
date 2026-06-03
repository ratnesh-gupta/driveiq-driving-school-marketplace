import { useAuthStore } from "@/lib/store";

/**
 * Returns the school ID of the currently logged-in school owner.
 * Dashboard pages should use this instead of a hardcoded ID.
 */
export function useSchoolId(): number | null {
  return useAuthStore((s) => s.schoolId);
}
