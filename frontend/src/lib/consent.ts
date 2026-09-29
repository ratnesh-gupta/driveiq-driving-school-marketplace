/**
 * Consent (DPDP). The server's /api/consents records are the source of truth
 * (DIQ-604); the localStorage keys here are only a cache so portals render
 * without a flash, plus a retry queue for grants that could not be sent.
 */

import { fetchMyConsents, postConsents, type ConsentPurpose } from "@/lib/ops-api";

export type AppRole = "school" | "admin" | "instructor" | "learner";

const ROLE_CONSENT_PREFIX = "driveiq_role_consent_v1:";
const REGISTER_CONSENT_KEY = "driveiq_register_consent_v1";

export type RoleConsentRecord = {
  role: AppRole;
  acceptedAt: string;
  version: number;
};

export const ROLE_CONSENT_VERSION = 1;

export function roleConsentKey(role: AppRole, userId?: number | string | null): string {
  return `${ROLE_CONSENT_PREFIX}${role}:${userId ?? "anon"}`;
}

export function hasRoleConsent(role: AppRole, userId?: number | string | null): boolean {
  try {
    const raw = localStorage.getItem(roleConsentKey(role, userId));
    if (!raw) return false;
    const parsed = JSON.parse(raw) as RoleConsentRecord;
    return parsed.version === ROLE_CONSENT_VERSION && parsed.role === role;
  } catch {
    return false;
  }
}

export function setRoleConsent(role: AppRole, userId?: number | string | null): void {
  const record: RoleConsentRecord = {
    role,
    acceptedAt: new Date().toISOString(),
    version: ROLE_CONSENT_VERSION,
  };
  try {
    localStorage.setItem(roleConsentKey(role, userId), JSON.stringify(record));
  } catch {
    /* ignore */
  }
}

export function clearRoleConsent(role: AppRole, userId?: number | string | null): void {
  try {
    localStorage.removeItem(roleConsentKey(role, userId));
  } catch {
    /* ignore */
  }
}

/** Version of the Terms / Privacy / processing notice shown at registration. */
export const POLICY_VERSION = "2026-09-29";

const PENDING_KEY = "driveiq_pending_consents_v1";
const DEVICE_KEY = "driveiq_device_id";

type Grant = { purpose: ConsentPurpose; version: string };

function readPending(): Grant[] {
  try {
    return JSON.parse(localStorage.getItem(PENDING_KEY) ?? "[]") as Grant[];
  } catch {
    return [];
  }
}

function writePending(grants: Grant[]): void {
  try {
    if (grants.length) localStorage.setItem(PENDING_KEY, JSON.stringify(grants));
    else localStorage.removeItem(PENDING_KEY);
  } catch {
    /* ignore */
  }
}

/** Send grants for the signed-in user; queue them for retry if that fails. */
export async function recordConsents(grants: Grant[]): Promise<boolean> {
  try {
    await postConsents(grants);
    return true;
  } catch {
    const pending = readPending();
    writePending([...pending, ...grants.filter((g) => !pending.some((p) => p.purpose === g.purpose && p.version === g.version))]);
    return false;
  }
}

/** Retry grants that could not be sent earlier (called when a portal opens). */
export async function flushPendingConsents(): Promise<void> {
  const pending = readPending();
  if (!pending.length) return;
  try {
    await postConsents(pending);
    writePending([]);
  } catch {
    /* keep for next time */
  }
}

/** Has the server recorded this user's portal consent at the current version? */
export async function hasServerRoleConsent(role: AppRole): Promise<boolean> {
  const rows = await fetchMyConsents();
  return rows.some(
    (c) => c.purpose === "role_portal" && c.role === role && c.version === String(ROLE_CONSENT_VERSION) && !c.withdrawnAt
  );
}

/** Random id for the anonymous cookie-banner record (no personal data). */
export function deviceId(): string | undefined {
  try {
    let id = localStorage.getItem(DEVICE_KEY);
    if (!id) {
      id = crypto.randomUUID();
      localStorage.setItem(DEVICE_KEY, id);
    }
    return id;
  } catch {
    return undefined;
  }
}

/** Registration checkboxes (all required by the form) + the portal notice for the new role. */
export async function saveRegisterConsent(payload: {
  terms: boolean;
  privacy: boolean;
  processing: boolean;
  role: string;
}): Promise<void> {
  try {
    localStorage.setItem(REGISTER_CONSENT_KEY, JSON.stringify({ ...payload, at: new Date().toISOString() }));
  } catch {
    /* ignore */
  }

  const grants: Grant[] = [];
  if (payload.terms) grants.push({ purpose: "terms", version: POLICY_VERSION });
  if (payload.privacy) grants.push({ purpose: "privacy", version: POLICY_VERSION });
  if (payload.processing) grants.push({ purpose: "processing", version: POLICY_VERSION });
  grants.push({ purpose: "role_portal", version: String(ROLE_CONSENT_VERSION) });
  await recordConsents(grants);
}

/** Role-specific processing summary shown at portal entry. */
export function roleProcessingPurposes(role: AppRole): string[] {
  switch (role) {
    case "school":
      return [
        "Operate your school account, team access, and subscription",
        "Manage leads, learners, instructors, schedules, vehicles, and payments records",
        "Send operational notifications and messages related to your school",
        "Analytics limited to your school for service improvement",
      ];
    case "instructor":
      return [
        "Show sessions assigned to you and record attendance",
        "Messaging with your school and assigned learners",
        "Profile and training-related notifications",
      ];
    case "learner":
      return [
        "Show your training progress, sessions, and documents",
        "Messaging with your school / instructor",
        "Inquiries and package-related records for your training",
      ];
    case "admin":
      return [
        "Platform administration: schools, users, moderation, analytics",
        "Handle data-subject and grievance requests",
        "Security, audit, and abuse prevention",
      ];
    default:
      return [
        "Account authentication and preferences",
        "Search, inquiries, and reviews you submit",
        "Optional location only when you choose nearby search",
      ];
  }
}
