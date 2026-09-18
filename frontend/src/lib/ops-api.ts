import { getStoredToken } from "@/lib/auth-api";

const API_BASE =
  (import.meta.env.VITE_API_BASE_URL as string | undefined)
    ?.replace(/\/+$/, "")
    .replace(/\/api$/i, "") ?? "";

function apiUrl(path: string): string {
  return `${API_BASE}${path}`;
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = getStoredToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    ...(init.headers as Record<string, string> | undefined),
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  if (init.body && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/json";
  }

  const res = await fetch(apiUrl(path), { ...init, headers });
  if (!res.ok) {
    let message = `Request failed (${res.status})`;
    try {
      const data = await res.json();
      if (data.message) message = data.message;
      else if (data.errors) message = Object.values(data.errors).flat().join(" ");
    } catch {
      /* ignore */
    }
    throw new Error(message);
  }
  if (res.status === 204) return undefined as T;
  const text = await res.text();
  return text ? JSON.parse(text) : (undefined as T);
}

// —— School analytics ——
export function fetchSchoolAnalytics(schoolId: number) {
  return request<Record<string, unknown>>(`/api/schools/${schoolId}/analytics`);
}

export function fetchSchoolInstructorAnalytics(schoolId: number) {
  return request<{ schoolId: number; instructors: unknown[] }>(
    `/api/schools/${schoolId}/analytics/instructors`
  );
}

export function fetchPlatformAnalytics() {
  return request<Record<string, unknown>>(`/api/admin/analytics`);
}

// —— Learners ——
export function listLearners(schoolId: number, status?: string) {
  const q = status ? `?status=${encodeURIComponent(status)}` : "";
  return request<unknown[]>(`/api/schools/${schoolId}/learners${q}`);
}

export function createLearner(schoolId: number, body: Record<string, unknown>) {
  return request(`/api/schools/${schoolId}/learners`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function convertInquiry(inquiryId: number, body: Record<string, unknown> = {}) {
  return request(`/api/inquiries/${inquiryId}/convert`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function assignLearner(learnerId: number, body: { instructorId?: number; vehicleId?: number }) {
  return request(`/api/learners/${learnerId}/assign`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function fetchLearnerProgress(learnerId: number) {
  return request<{ overallCompletion: number; skills: { skillName: string; percentage: number }[] }>(
    `/api/learners/${learnerId}/progress`
  );
}

export function fetchLearnerMe() {
  return request<{ learner: Record<string, unknown>; upcomingSessions: unknown[] }>(`/api/learner/me`);
}

// —— Instructors ——
export function listInstructors(schoolId: number) {
  return request<unknown[]>(`/api/schools/${schoolId}/instructors`);
}

export function createInstructor(schoolId: number, body: Record<string, unknown>) {
  return request(`/api/schools/${schoolId}/instructors`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function fetchInstructorMe() {
  return request<Record<string, unknown>>(`/api/instructor/me`);
}

// —— Schedules / vehicles ——
export function listSchedules(schoolId: number, params?: { from?: string; to?: string }) {
  const sp = new URLSearchParams();
  if (params?.from) sp.set("from", params.from);
  if (params?.to) sp.set("to", params.to);
  const q = sp.toString() ? `?${sp}` : "";
  return request<unknown[]>(`/api/schools/${schoolId}/schedules${q}`);
}

export function createSchedule(schoolId: number, body: Record<string, unknown>) {
  return request(`/api/schools/${schoolId}/schedules`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function markAttendance(scheduleId: number, body: { status: string; sessionSummary?: string }) {
  return request(`/api/schedules/${scheduleId}/attendance`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function listVehicles(schoolId: number) {
  return request<unknown[]>(`/api/schools/${schoolId}/vehicles`);
}

// —— Messaging ——
export function listMessageThreads() {
  return request<
    {
      id: number;
      otherUser: { id: number; name: string; role: string } | null;
      lastMessagePreview: string | null;
      unreadCount: number;
      lastMessageAt: string | null;
    }[]
  >(`/api/messages/threads`);
}

export function getMessageThread(threadId: number) {
  return request<{
    id: number;
    otherUser: { id: number; name: string; role: string } | null;
    messages: { id: number; senderId: number; senderName?: string; body: string; createdAt: string }[];
  }>(`/api/messages/threads/${threadId}`);
}

export function sendMessage(body: { receiverId?: number; threadId?: number; body: string }) {
  return request(`/api/messages`, { method: "POST", body: JSON.stringify(body) });
}

export function unreadMessageCount() {
  return request<{ unreadCount: number }>(`/api/messages/unread-count`);
}
