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
};

const API_BASE = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.replace(/\/+$/, "") ?? "";

function apiUrl(path: string): string {
  return `${API_BASE}${path}`;
}

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

async function parseJson<T>(res: Response): Promise<T> {
  const text = await res.text();
  return text ? (JSON.parse(text) as T) : ({} as T);
}

export async function registerApi(payload: {
  name: string;
  email: string;
  password: string;
  role: UserRole;
}): Promise<AuthResponse> {
  const res = await fetch(apiUrl("/api/auth/register"), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });

  if (!res.ok) throw new Error("Registration failed");
  return parseJson<AuthResponse>(res);
}

export async function loginApi(payload: { email: string; password: string }): Promise<AuthResponse> {
  const res = await fetch(apiUrl("/api/auth/login"), {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });

  if (!res.ok) throw new Error("Invalid credentials");
  return parseJson<AuthResponse>(res);
}

export async function meApi(token: string): Promise<AuthUser> {
  const res = await fetch(apiUrl("/api/auth/me"), {
    method: "GET",
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: "application/json",
    },
  });

  if (!res.ok) throw new Error("Session expired");
  const data = await parseJson<{ user: AuthUser }>(res);
  return data.user;
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
