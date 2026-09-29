import { getStoredToken, handleUnauthorized } from "@/lib/auth-api";

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
  // FormData sets its own multipart boundary header.
  if (init.body && !(init.body instanceof FormData) && !headers["Content-Type"]) {
    headers["Content-Type"] = "application/json";
  }

  const res = await fetch(apiUrl(path), { ...init, headers });
  if (res.status === 401 && token) handleUnauthorized();
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
  return request<{ instructor?: Record<string, unknown>; dashboard?: Record<string, unknown> } & Record<string, unknown>>(`/api/instructor/me`);
}

export function listInstructorSessions() {
  return request<unknown[]>(`/api/instructor/sessions`);
}

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

export type PaymentRow = {
  id: number;
  schoolId: number;
  invoiceId: number | null;
  learnerId: number | null;
  learnerName?: string | null;
  purpose: string;
  packageId: number | null;
  packageName?: string | null;
  amount: number;
  currency: string;
  method: string;
  status: string;
  provider?: string | null;
  receipt?: string | null;
  paidAt?: string | null;
  createdAt?: string | null;
};

export function listPayments(schoolId: number) {
  return request<PaymentRow[]>(`/api/schools/${schoolId}/payments`);
}

export function purchasePackage(
  schoolId: number,
  body: { learnerId: number; packageId: number; method?: string; markPaid?: boolean }
) {
  return request<{ payment: PaymentRow; invoice: Record<string, unknown>; gateway: Record<string, unknown> | null }>(
    `/api/schools/${schoolId}/payments/package`,
    { method: "POST", body: JSON.stringify(body) }
  );
}

export function markPaymentPaid(paymentId: number, providerPaymentId?: string) {
  return request<PaymentRow>(`/api/payments/${paymentId}/mark-paid`, {
    method: "POST",
    body: JSON.stringify({ providerPaymentId }),
  });
}

export function markPaymentFailed(paymentId: number, notes?: string) {
  return request<PaymentRow>(`/api/payments/${paymentId}/mark-failed`, {
    method: "POST",
    body: JSON.stringify({ notes }),
  });
}

/** Schools cannot moderate reviews; they report them to platform admins. */
export function reportReview(reviewId: number, body: { reason: string; details?: string }) {
  return request<{ message: string; id: number }>(`/api/reviews/${reviewId}/report`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

// ── Team (DIQ-403) ──────────────────────────────────────────────

export type TeamMember = {
  id: number;
  userId: number | null;
  name: string | null;
  email: string | null;
  role: "owner" | "manager";
  status: "pending" | "active";
  invitedAt: string | null;
  acceptedAt: string | null;
  inviteExpiresAt: string | null;
};

export function listTeam(schoolId: number) {
  return request<TeamMember[]>(`/api/schools/${schoolId}/team`);
}

export function inviteManager(schoolId: number, email: string) {
  return request<TeamMember>(`/api/schools/${schoolId}/team`, {
    method: "POST",
    body: JSON.stringify({ email, role: "manager" }),
  });
}

export function removeTeamMember(schoolId: number, memberId: number) {
  return request<void>(`/api/schools/${schoolId}/team/${memberId}`, { method: "DELETE" });
}

export type InvitationPreview = {
  schoolName: string;
  email: string;
  role: "manager";
  hasAccount: boolean;
  expiresAt: string | null;
};

export function getInvitation(token: string) {
  return request<InvitationPreview>(`/api/team/invitations/${encodeURIComponent(token)}`);
}

export function acceptInvitation(body: {
  token: string;
  name?: string;
  password?: string;
  password_confirmation?: string;
}) {
  return request<{ token: string | null; schoolId: number; schoolRole: "manager" }>(`/api/team/accept`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

// ── One-time inquiry review link (DIQ-407) ──────────────────────

export type InquiryReviewPreview = {
  schoolId: number;
  schoolName: string;
  schoolSlug: string;
  authorName: string;
};

export function getInquiryReview(token: string) {
  return request<InquiryReviewPreview>(`/api/reviews/via-inquiry/${encodeURIComponent(token)}`);
}

export function submitInquiryReview(body: { token: string; authorName: string; rating: number; content: string }) {
  return request<{ id: number }>(`/api/reviews/via-inquiry`, { method: "POST", body: JSON.stringify(body) });
}

// ── School comparison (DIQ-505) ─────────────────────────────────

export type ComparisonBadges = {
  bestRated: number | null;
  mostAffordable: number | null;
  bestValue: number | null;
  mostReviewed: number | null;
  womenFriendly: number[];
};

export type CompareResponse<TSchool> = {
  schools: (TSchool & {
    packageSummary: { count: number; minPrice: number | null; maxPrice: number | null };
    reviewSummary: {
      count: number;
      average: number | null;
      topReview: { authorName: string; rating: number; content: string } | null;
    };
  })[];
  badges: ComparisonBadges;
  missingIds: number[];
};

export function compareSchools<TSchool>(ids: number[]) {
  return request<CompareResponse<TSchool>>(`/api/schools/compare?ids=${ids.join(",")}`);
}

// ── Admin school list with totals (DIQ-506) ─────────────────────

/** Like GET /api/schools but also returns X-Total-Count for pagination. */
export async function listSchoolsPage<TSchool>(params: { limit: number; offset: number }) {
  const token = getStoredToken();
  const res = await fetch(apiUrl(`/api/schools?limit=${params.limit}&offset=${params.offset}`), {
    headers: { Accept: "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });
  if (!res.ok) throw new Error(`Request failed (${res.status})`);
  return {
    schools: (await res.json()) as TSchool[],
    total: Number(res.headers.get("X-Total-Count") ?? 0),
  };
}

export type VerificationFlags = {
  verified: boolean;
  phoneVerified: boolean;
  businessVerified: boolean;
  locationVerified: boolean;
  premiumVerified: boolean;
};

export function updateSchoolVerification(schoolId: number, flags: VerificationFlags) {
  return request(`/api/schools/${schoolId}`, { method: "PATCH", body: JSON.stringify(flags) });
}

// ── Documents (DIQ-601): private uploads, opened through short-lived signed links ──

export type DocumentOwnerKind = "learner" | "instructor" | "vehicle";

export type DocumentRow = {
  id: number;
  type: string;
  hasFile: boolean;
  fileName: string | null;
  status: string;
  expiryDate?: string | null;
  notes?: string | null;
  verifiedAt?: string | null;
  createdAt?: string | null;
};

const DOCUMENT_BASE: Record<DocumentOwnerKind, string> = {
  learner: "/api/learners",
  instructor: "/api/instructors",
  vehicle: "/api/vehicles",
};

export function listDocuments(kind: DocumentOwnerKind, ownerId: number) {
  return request<DocumentRow[]>(`${DOCUMENT_BASE[kind]}/${ownerId}/documents`);
}

export function uploadDocument(
  kind: DocumentOwnerKind,
  ownerId: number,
  body: { type: string; file: File; expiryDate?: string }
) {
  const form = new FormData();
  form.append("type", body.type);
  form.append("file", body.file);
  if (body.expiryDate) form.append("expiryDate", body.expiryDate);
  return request<DocumentRow>(`${DOCUMENT_BASE[kind]}/${ownerId}/documents`, { method: "POST", body: form });
}

/** Staff review (learner and instructor documents only). */
export function reviewDocument(
  kind: Exclude<DocumentOwnerKind, "vehicle">,
  ownerId: number,
  docId: number,
  status: "verified" | "rejected"
) {
  return request<DocumentRow>(`${DOCUMENT_BASE[kind]}/${ownerId}/documents/${docId}`, {
    method: "PATCH",
    body: JSON.stringify({ status }),
  });
}

export function getDocumentLink(kind: DocumentOwnerKind, docId: number) {
  return request<{ url: string; expiresAt: string }>(`/api/documents/${kind}/${docId}/link`);
}

// ── Admin users (DIQ-602) ──

export type AdminUserRow = {
  id: number;
  name: string;
  email: string;
  role: "admin" | "school" | "instructor" | "learner";
  schoolId: number | null;
  schoolName: string | null;
  active: boolean;
  deactivatedAt: string | null;
  createdAt: string | null;
};

export type Paged<T> = { data: T[]; meta: { page: number; lastPage: number; total: number } };

export function listAdminUsers(params: { search?: string; role?: string; status?: string; page?: number }) {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== "") q.set(k, String(v));
  });
  return request<Paged<AdminUserRow>>(`/api/admin/users?${q.toString()}`);
}

export function setAdminUserActive(userId: number, active: boolean) {
  return request<AdminUserRow>(`/api/admin/users/${userId}`, {
    method: "PATCH",
    body: JSON.stringify({ active }),
  });
}

// ── Contact form (DIQ-603) ──

export type ContactMessageRow = {
  id: number;
  name: string;
  email: string;
  subject: string;
  message: string;
  status: "new" | "read" | "closed";
  userId: number | null;
  handledAt: string | null;
  createdAt: string | null;
};

export function sendContactMessage(body: {
  name: string;
  email: string;
  subject: string;
  message: string;
  website: string;
  formStartedAt: number;
}) {
  return request<{ id: number; message: string }>(`/api/contact`, { method: "POST", body: JSON.stringify(body) });
}

export function listContactMessages(params: { status?: string; page?: number }) {
  const q = new URLSearchParams();
  if (params.status) q.set("status", params.status);
  if (params.page) q.set("page", String(params.page));
  return request<Paged<ContactMessageRow> & { meta: { unread: number } }>(`/api/admin/contact-messages?${q.toString()}`);
}

export function updateContactMessage(id: number, status: ContactMessageRow["status"]) {
  return request<ContactMessageRow>(`/api/admin/contact-messages/${id}`, {
    method: "PATCH",
    body: JSON.stringify({ status }),
  });
}

// ── Consent records (DIQ-604) ──

export type ConsentPurpose = "terms" | "privacy" | "processing" | "role_portal" | "cookies_optional";

export type ConsentRow = {
  id: number;
  purpose: ConsentPurpose;
  role: string | null;
  version: string;
  grantedAt: string;
  withdrawnAt: string | null;
};

export function postConsents(consents: { purpose: ConsentPurpose; version: string }[], deviceId?: string) {
  return request<ConsentRow[]>(`/api/consents`, {
    method: "POST",
    body: JSON.stringify({ consents, deviceId }),
  });
}

export function fetchMyConsents() {
  return request<ConsentRow[]>(`/api/consents`);
}

export function withdrawConsent(purpose: ConsentPurpose) {
  return request<void>(`/api/consents/${purpose}`, { method: "DELETE" });
}
