export type UserRole = "user" | "school" | "admin";

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role: UserRole;
};

type AuthResponse = {
  user: AuthUser;
  token: string;
  schoolId: number | null;
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
  return text ? JSON.parse(text) : {};
}

export async function loginApi(payload: { email: string; password: string }): Promise<AuthResponse> {
  const res = await fetch(apiUrl("/api/auth/login"), {
    method: "POST",
    headers: JSON_HEADERS,
    body: JSON.stringify(payload),
  });

  if (!res.ok) await handleError(res, "Invalid credentials");
  const text = await res.text();
  return text ? JSON.parse(text) : {};
}

export async function meApi(token: string): Promise<{ user: AuthUser; schoolId: number | null }> {
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
  return { user: data.user, schoolId: data.schoolId ?? null };
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
