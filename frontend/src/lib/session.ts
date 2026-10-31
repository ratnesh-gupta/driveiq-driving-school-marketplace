import { queryClient } from "@/lib/query-client";
import { teardownEcho } from "@/lib/realtime";
import { useComparisonStore } from "@/features/comparison/stores/comparison-store";
import { useLocationStore } from "@/features/geo-rules/stores/location-store";

/** Per-user localStorage keys (prefixes). `driveiq_device_id` is anonymous and stays. */
const USER_STORAGE_PREFIXES = [
  "driveiq_role_consent_v1:",
  "driveiq_register_consent_v1",
  "driveiq_pending_consents_v1",
];

function clearUserStorage(): void {
  try {
    for (const key of Object.keys(localStorage)) {
      if (USER_STORAGE_PREFIXES.some((p) => key.startsWith(p))) localStorage.removeItem(key);
    }
  } catch {
    /* ignore */
  }
}

/**
 * Drops everything tied to the signed-in user (FSEC-01/05/18): cached server
 * data, the realtime socket, per-user storage and per-session UI stores.
 * Auth store and token are reset by the caller (store.ts). Login passes
 * keepLocation so an anonymous visitor's chosen city survives signing in.
 */
export function clearSessionData(opts: { keepLocation?: boolean } = {}): void {
  queryClient.clear();
  teardownEcho();
  clearUserStorage();
  useComparisonStore.getState().clearAll();
  if (!opts.keepLocation) useLocationStore.getState().clearLocation();
}
