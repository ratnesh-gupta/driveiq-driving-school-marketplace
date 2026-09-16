const API_BASE =
  (import.meta.env.VITE_API_BASE_URL as string | undefined)
    ?.replace(/\/+$/, "")
    .replace(/\/api$/i, "") ?? "";

function apiUrl(path: string): string {
  return `${API_BASE}${path}`;
}

export type AppNotification = {
  id: string;
  type: string;
  title: string;
  body: string | null;
  data: Record<string, unknown> | null;
  schoolId: number | null;
  readAt: string | null;
  createdAt: string | null;
};

export type NotificationsResponse = {
  data: AppNotification[];
  unreadCount: number;
};

async function authFetch(path: string, token: string, init: RequestInit = {}): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  headers.set("Authorization", `Bearer ${token}`);
  if (init.body && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  return fetch(apiUrl(path), { ...init, headers });
}

export async function listNotifications(
  token: string,
  opts?: { unreadOnly?: boolean; limit?: number },
): Promise<NotificationsResponse> {
  const params = new URLSearchParams();
  if (opts?.unreadOnly) params.set("unreadOnly", "1");
  if (opts?.limit) params.set("limit", String(opts.limit));

  const qs = params.toString();
  const res = await authFetch(`/api/notifications${qs ? `?${qs}` : ""}`, token);

  if (!res.ok) {
    throw new Error("Failed to load notifications");
  }

  return res.json();
}

export async function markNotificationRead(token: string, id: string): Promise<AppNotification> {
  const res = await authFetch(`/api/notifications/${id}/read`, token, { method: "POST" });

  if (!res.ok) {
    throw new Error("Failed to mark notification as read");
  }

  return res.json();
}

export async function markAllNotificationsRead(token: string): Promise<{ updated: number }> {
  const res = await authFetch("/api/notifications/read-all", token, { method: "POST" });

  if (!res.ok) {
    throw new Error("Failed to mark all notifications as read");
  }

  return res.json();
}
