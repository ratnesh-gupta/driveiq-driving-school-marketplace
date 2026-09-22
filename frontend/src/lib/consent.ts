/** Client-side consent keys (DPDP-minded). Not a substitute for server-side records. */

export type AppRole = "user" | "school" | "admin" | "instructor" | "learner";

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

export function saveRegisterConsent(payload: {
  terms: boolean;
  privacy: boolean;
  processing: boolean;
  role: string;
}): void {
  try {
    localStorage.setItem(
      REGISTER_CONSENT_KEY,
      JSON.stringify({ ...payload, at: new Date().toISOString() })
    );
  } catch {
    /* ignore */
  }
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
