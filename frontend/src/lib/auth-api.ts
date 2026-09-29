export type UserRole = "school" | "admin" | "instructor" | "learner";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
};

/** Owner vs manager, for school staff only (DIQ-302). */
export type SchoolRole = "owner" | "manager";

type AuthResponse = {
  user: AuthUser;
  token: string;
  schoolId: number | null;
  schoolRole?: SchoolRole | null;
};

export type FieldErrors = Record<string, string[]>;

export class ApiValidationError extends Error {
  fieldErrors: FieldErrors;

  constructor(message: string, fieldErrors: FieldErrors) {
    super(message);
    this.name = "ApiValidationError";
    this.fieldErrors = fieldErrors;
  }
}

const API_BASE =
  (import.meta.env.VITE_API_BASE_URL as string | undefined)
    ?.replace(/\/+$/, "")
    .replace(/\/api$/i, "") ?? "";

function apiUrl(path: string): string {
  return `${API_BASE}${path}`;
}

const JSON_HEADERS = {
  "Content-Type": "application/json",
  Accept: "application/json",
};

export function getStoredToken(): string | null {
  return localStorage.getItem("driveiq_auth_token");
}

export function setStoredToken(token: string | null): void {
  if (!token) {
    localStorage.removeItem("driveiq_auth_token");
    return;
  }
  localStorage.setItem("driveiq_auth_token", token);
}

const PORTAL_PREFIXES = ["/dashboard", "/admin", "/instructor", "/learner"];

/**
 * A signed-in request came back 401: the token expired or was revoked.
 * Drop it and, inside a portal, send the user to login.
 */
export function handleUnauthorized(): void {
  if (!getStoredToken()) return;
  setStoredToken(null);
  const path = window.location.pathname;
  if (PORTAL_PREFIXES.some((p) => path === p || path.startsWith(`${p}/`))) {
    window.location.assign("/auth/login?expired=1");
  }
}

async function handleError(res: Response, fallbackMessage: string): Promise<never> {
  let message = fallbackMessage;
  let fieldErrors: FieldErrors | undefined;

  try {
    const data = await res.json();

    if (data.errors && typeof data.errors === "object") {
      fieldErrors = data.errors as FieldErrors;
      const allMessages = Object.values(fieldErrors).flat();
      message = allMessages.join(" ");
    } else if (data.message) {
      message = data.message;
    }
  } catch {
    // response wasn't JSON
  }

  if (fieldErrors) {
    throw new ApiValidationError(message, fieldErrors);
  }
  throw new Error(message);
}

export async function registerApi(payload: {
  name: string;
  email: string;
  password: string;
  role: UserRole;
}): Promise<AuthResponse> {
  const res = await fetch(apiUrl("/api/auth/register"), {
    method: "POST",
    headers: JSON_HEADERS,
    body: JSON.stringify(payload),
  });

  if (!res.ok) await handleError(res, "Registration failed");
  const text = await res.text();
  if (!text) throw new Error("Empty response from server");
  return JSON.parse(text) as AuthResponse;
}

export async function loginApi(payload: { email: string; password: string }): Promise<AuthResponse> {
  const res = await fetch(apiUrl("/api/auth/login"), {
    method: "POST",
    headers: JSON_HEADERS,
    body: JSON.stringify(payload),
  });

  if (!res.ok) await handleError(res, "Invalid credentials");
  const text = await res.text();
  if (!text) throw new Error("Empty response from server");
  return JSON.parse(text) as AuthResponse;
}

export async function meApi(token: string): Promise<{ user: AuthUser; schoolId: number | null; schoolRole: SchoolRole | null }> {
  const res = await fetch(apiUrl("/api/auth/me"), {
    method: "GET",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });

  if (!res.ok) await handleError(res, "Session expired");
  const text = await res.text();
  const data = text ? JSON.parse(text) : {};
  return { user: data.user, schoolId: data.schoolId ?? null, schoolRole: data.schoolRole ?? null };
}

export async function logoutApi(token: string): Promise<void> {
  await fetch(apiUrl("/api/auth/logout"), {
    method: "POST",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });
}

export function roleHomePath(role: UserRole | null | undefined): string {
  switch (role) {
    case "admin":
      return "/admin";
    case "school":
      return "/dashboard";
    case "instructor":
      return "/instructor";
    case "learner":
      return "/learner";
    default:
      return "/search";
  }
}

export async function forgotPasswordApi(email: string): Promise<void> {
  const res = await fetch(apiUrl("/api/auth/forgot-password"), {
    method: "POST",
    headers: JSON_HEADERS,
    body: JSON.stringify({ email }),
  });
  if (!res.ok) await handleError(res, "Could not send reset link");
}

export async function resetPasswordApi(payload: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  const res = await fetch(apiUrl("/api/auth/reset-password"), {
    method: "POST",
    headers: JSON_HEADERS,
    body: JSON.stringify(payload),
  });
  if (!res.ok) await handleError(res, "This reset link is invalid or has expired.");
}
