import { getStoredToken, handleUnauthorized } from "@/lib/auth-api";
import { announcePlanRequired, type PlanFeature } from "@/lib/plan";

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
      if (res.status === 402) announcePlanRequired(data);
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

export function listInstructorSessions(params?: { from?: string; to?: string }) {
  const sp = new URLSearchParams();
  if (params?.from) sp.set("from", params.from);
  if (params?.to) sp.set("to", params.to);
  const q = sp.toString() ? `?${sp}` : "";
  return request<unknown[]>(`/api/instructor/sessions${q}`);
}

export function listSchedules(schoolId: number, params?: { from?: string; to?: string; instructorId?: number }) {
  const sp = new URLSearchParams();
  if (params?.from) sp.set("from", params.from);
  if (params?.to) sp.set("to", params.to);
  if (params?.instructorId) sp.set("instructorId", String(params.instructorId));
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
  return request<{ payment: PaymentRow; invoice: Record<string, unknown> }>(
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
  /** Vehicle papers only: valid but lapsing within 30 days. */
  expiringSoon?: boolean;
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

// ── Lead notes & timeline (DIQ-706) ──

export type LeadTimelineEvent =
  | { type: "created"; at: string; channel?: string | null }
  | { type: "status"; at: string; from: string | null; to: string; by: string | null }
  | { type: "note"; id: number; at: string; body: string; followUpAt: string | null; by: string | null };

export function fetchLeadTimeline(inquiryId: number) {
  return request<{ inquiryId: number; status: string; nextFollowUpAt: string | null; events: LeadTimelineEvent[] }>(
    `/api/inquiries/${inquiryId}/timeline`
  );
}

export function addLeadNote(inquiryId: number, body: { body: string; followUpAt?: string }) {
  return request<LeadTimelineEvent>(`/api/inquiries/${inquiryId}/notes`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

// ── School settings (DIQ-707) ──

export type SchoolSettings = {
  notifications: {
    email: boolean;
    sms: boolean;
    in_app: boolean;
    new_inquiry: boolean;
    new_review: boolean;
    reminder_after_minutes: number;
  };
  timezone: string;
  locale: string;
  lead_auto_assign: boolean;
};

export function fetchSchoolSettings(schoolId: number) {
  return request<{ schoolId: number; settings: SchoolSettings }>(`/api/schools/${schoolId}/settings`);
}

export function saveSchoolSettings(schoolId: number, settings: Partial<SchoolSettings>) {
  return request<{ schoolId: number; settings: SchoolSettings }>(`/api/schools/${schoolId}/settings`, {
    method: "PUT",
    body: JSON.stringify({ settings }),
  });
}

// ── Lead response time (DIQ-703/708) ──

export type ResponseTimeSummary = {
  windowDays: number;
  leads: number;
  responded: number;
  awaitingReply: number;
  medianSeconds: number | null;
  averageSeconds: number | null;
  within1hRate: number;
  within24hRate: number;
};

export function fetchSchoolDashboard(schoolId: number) {
  return request<{ schoolId: number; metrics: { responseTime: ResponseTimeSummary } & Record<string, unknown> }>(
    `/api/schools/${schoolId}/dashboard`
  );
}

// ── Plan entitlements (DIQ-802) ──

export type Entitlements = {
  schoolId: number;
  plan: string;
  planExpiresAt: string | null;
  trial: { active: boolean; endsAt: string | null; plan: string };
  features: PlanFeature[];
  lockedFeatures: PlanFeature[];
  enforced: boolean;
};

export function fetchEntitlements(schoolId: number) {
  return request<Entitlements>(`/api/schools/${schoolId}/entitlements`);
}

// ── Plans & platform billing (DIQ-803/804) ──

export type PlanRow = {
  id: number;
  code: string;
  name: string;
  priceMonthly: number;
  features: Record<string, boolean> | null;
  rankingBoost: number;
  isSponsored: boolean;
  homepageFeatured: boolean;
};

export type PlatformInvoice = {
  id: number;
  invoiceNumber: string;
  schoolId: number | null;
  schoolName: string;
  billedToGstin: string | null;
  planCode: string;
  months: number;
  subtotal: number;
  gstRate: number;
  gstAmount: number;
  total: number;
  currency: string;
  status: "issued" | "paid" | "void";
  issuedAt: string;
  dueAt: string;
  paymentReference: string | null;
  paidAt: string | null;
  voidedAt: string | null;
  voidReason: string | null;
  seller: { name?: string; address?: string; gstin?: string | null; email?: string };
  paymentInstructions?: {
    upi_id?: string;
    bank_name?: string;
    account_name?: string;
    account_number?: string;
    ifsc?: string;
  };
};

export function listPlans() {
  return request<PlanRow[]>(`/api/plans`);
}

export function listSchoolInvoices(schoolId: number) {
  return request<PlatformInvoice[]>(`/api/schools/${schoolId}/billing/invoices`);
}

export function fetchSchoolInvoice(schoolId: number, invoiceId: number) {
  return request<PlatformInvoice>(`/api/schools/${schoolId}/billing/invoices/${invoiceId}`);
}

export function requestPlanInvoice(schoolId: number, body: { planCode: string; months: number; gstin?: string }) {
  return request<PlatformInvoice>(`/api/schools/${schoolId}/billing/invoices`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function cancelPlanInvoice(schoolId: number, invoiceId: number) {
  return request<PlatformInvoice>(`/api/schools/${schoolId}/billing/invoices/${invoiceId}/cancel`, { method: "POST" });
}

// ── Admin monetization console (DIQ-806) ──

export type MonetizationOverview = {
  activeSubscriptions: number;
  activePaid: number;
  byTier: Record<string, number>;
  mrr: number;
  arr: number;
  mrrByTier: Record<string, number>;
  trials: number;
  churn30d: { churned: number; paidAtStart: number; rate: number };
  expiringSoon: { id: number; schoolId: number; schoolName: string | null; planCode: string | null; expiresAt: string | null }[];
  trialsEndingSoon: { id: number; schoolId: number; schoolName: string | null; planCode: string | null; expiresAt: string | null }[];
  sponsoredSlots: { perPage: number; eligibleSchools: number; utilization: number };
  pendingInvoices: { count: number; total: number };
};

export type AdminSubscriptionRow = {
  id: number;
  schoolId: number;
  schoolName: string | null;
  planCode: string | null;
  priceMonthly: number | null;
  status: string;
  startsAt: string | null;
  expiresAt: string | null;
  current: boolean;
  notes: string | null;
};

export type PlacementRow = {
  id: number;
  schoolId: number;
  schoolName: string | null;
  placement: "search_top" | "homepage" | "locality";
  localityId: number | null;
  localityName: string | null;
  startsAt: string;
  endsAt: string;
  live: boolean;
  notes: string | null;
};

export const fetchMonetizationOverview = () => request<MonetizationOverview>(`/api/admin/subscriptions/overview`);

export function listAdminInvoices(status?: string) {
  return request<Paged<PlatformInvoice>>(`/api/admin/billing/invoices${status ? `?status=${status}` : ""}`);
}

export function recordInvoicePayment(id: number, body: { reference: string; paidAt?: string }) {
  return request<PlatformInvoice>(`/api/admin/billing/invoices/${id}/record-payment`, { method: "POST", body: JSON.stringify(body) });
}

export function voidInvoice(id: number, reason?: string) {
  return request<PlatformInvoice>(`/api/admin/billing/invoices/${id}/void`, { method: "POST", body: JSON.stringify({ reason }) });
}

export function listAdminSubscriptions(params: { status?: string; search?: string; page?: number }) {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => v !== undefined && v !== "" && q.set(k, String(v)));
  return request<Paged<AdminSubscriptionRow>>(`/api/admin/subscriptions?${q.toString()}`);
}

export function assignSubscription(body: { schoolId: number; planCode: string; months: number; notes?: string }) {
  return request(`/api/admin/subscriptions`, { method: "POST", body: JSON.stringify(body) });
}

export function cancelSubscription(schoolId: number) {
  return request(`/api/admin/subscriptions/${schoolId}/cancel`, { method: "POST" });
}

export const listPlacements = () => request<PlacementRow[]>(`/api/admin/placements`);

export function createPlacement(body: {
  schoolId: number;
  placement: PlacementRow["placement"];
  localityId?: number;
  startsAt: string;
  endsAt: string;
  notes?: string;
}) {
  return request<PlacementRow>(`/api/admin/placements`, { method: "POST", body: JSON.stringify(body) });
}

export const endPlacement = (id: number) => request<PlacementRow>(`/api/admin/placements/${id}/end`, { method: "POST" });

export type MarketplaceSettings = { sponsored_slots_per_page: number; homepage_slots: number };

export const fetchMarketplaceSettings = () => request<MarketplaceSettings>(`/api/admin/marketplace-settings`);

export function saveMarketplaceSettings(body: Partial<MarketplaceSettings>) {
  return request<MarketplaceSettings>(`/api/admin/marketplace-settings`, { method: "PUT", body: JSON.stringify(body) });
}

/* ---------- Learner detail (DIQ-906) ---------- */

export type LearnerDetail = {
  id: number;
  schoolId: number;
  name: string;
  mobile?: string | null;
  email?: string | null;
  gender?: string | null;
  dob?: string | null;
  address?: string | null;
  emergencyContact?: string | null;
  vehicleType?: string | null;
  status: string;
  packageId?: number | null;
  packageName?: string | null;
  assignedInstructorId?: number | null;
  instructorName?: string | null;
  assignedVehicleId?: number | null;
  vehicleRegistration?: string | null;
  startDate?: string | null;
  expectedCompletionDate?: string | null;
  learnerLicenseNumber?: string | null;
  licenseIssueDate?: string | null;
  licenseExpiryDate?: string | null;
  permanentLicenseStatus?: string | null;
  notes?: string | null;
  userId?: number | null;
};

export type AssignmentRow = {
  id: number;
  action: string;
  instructorName: string | null;
  vehicleRegistration: string | null;
  assignedBy: string | null;
  notes: string | null;
  createdAt: string;
};

export type DrivingTestRow = {
  id: number;
  learnerId: number;
  testDate: string;
  rtoName: string | null;
  rtoLocation: string | null;
  attemptNumber: number;
  status: "scheduled" | "completed" | "passed" | "failed";
  notes: string | null;
};

export type SessionHistoryRow = {
  id: number;
  sessionDate: string;
  startTime: string;
  endTime: string;
  status: string;
  instructorName: string | null;
  pickupLocation: string | null;
  sessionSummary: string | null;
  notes: string | null;
  attendance: string | null;
};

export type SkillProgress = { skillName: string; percentage: number; notes?: string | null; updatedAt?: string | null };

export function getLearner(id: number) {
  return request<LearnerDetail>(`/api/learners/${id}`);
}

export function updateLearner(id: number, body: Record<string, unknown>) {
  return request<LearnerDetail>(`/api/learners/${id}`, { method: "PATCH", body: JSON.stringify(body) });
}

export function listLearnerAssignments(id: number) {
  return request<AssignmentRow[]>(`/api/learners/${id}/assignments`);
}

export function listTrainingSkills() {
  return request<{ skills: { code: string; label: string }[] }>(`/api/training-skills`);
}

export function updateLearnerProgress(id: number, body: { skillName: string; percentage: number; notes?: string; sessionId?: number }) {
  return request<{ overallCompletion: number; skills: SkillProgress[] }>(`/api/learners/${id}/progress`, {
    method: "PUT",
    body: JSON.stringify(body),
  });
}

export function listLearnerSessions(id: number) {
  return request<SessionHistoryRow[]>(`/api/learners/${id}/sessions`);
}

export function listDrivingTests(learnerId: number) {
  return request<DrivingTestRow[]>(`/api/learners/${learnerId}/driving-tests`);
}

export function createDrivingTest(learnerId: number, body: Record<string, unknown>) {
  return request<DrivingTestRow>(`/api/learners/${learnerId}/driving-tests`, { method: "POST", body: JSON.stringify(body) });
}

export function updateDrivingTest(id: number, body: Record<string, unknown>) {
  return request<DrivingTestRow>(`/api/driving-tests/${id}`, { method: "PATCH", body: JSON.stringify(body) });
}

/* ---------- Instructor management (DIQ-907) ---------- */

export type InstructorDetail = {
  id: number;
  schoolId: number;
  name: string;
  status: "active" | "inactive" | "terminated";
  gender?: string | null;
  mobile?: string | null;
  email?: string | null;
  dob?: string | null;
  address?: string | null;
  employeeId?: string | null;
  joiningDate?: string | null;
  employmentType?: "full_time" | "part_time" | "contract" | null;
  licenseNumber?: string | null;
  licenseCategory?: string | null;
  licenseExpiry?: string | null;
  yearsExperience?: number;
  skills?: string[];
  languages?: string[];
  womenInstructor?: boolean;
  publicVisible?: boolean;
  bio?: string | null;
  totalLearnersTrained?: number;
  hasLogin?: boolean;
};

export type InstructorPerformanceRow = {
  instructorId: number;
  name: string;
  sessionsCompleted: number;
  sessionsTotal: number;
  learnersAssigned: number;
  attendanceRate: number;
  completionRate: number;
  hoursThisWeek: number;
  totalLearnersTrained: number;
};

export function updateInstructor(id: number, body: Record<string, unknown>) {
  return request<InstructorDetail>(`/api/instructors/${id}`, { method: "PATCH", body: JSON.stringify(body) });
}

export function sendInstructorLogin(id: number, email?: string) {
  return request<InstructorDetail>(`/api/instructors/${id}/login`, {
    method: "POST",
    body: JSON.stringify(email ? { email } : {}),
  });
}

export function removeInstructor(id: number) {
  return request<void>(`/api/instructors/${id}`, { method: "DELETE" });
}

/* ---------- Instructor portal (DIQ-908) ---------- */

export type MyLearnerRow = {
  id: number;
  name: string;
  mobile: string | null;
  status: string;
  vehicleType: string | null;
  packageName: string | null;
  assignedToMe: boolean;
  overallCompletion: number;
  nextSessionDate: string | null;
};

export type LeaveRow = {
  id: number;
  instructorId: number;
  instructorName: string | null;
  startDate: string;
  endDate: string;
  reason: string | null;
  status: "pending" | "approved" | "rejected";
  reviewedAt: string | null;
};

export function listMyLearners() {
  return request<MyLearnerRow[]>(`/api/instructor/learners`);
}

export function listMyLeave() {
  return request<LeaveRow[]>(`/api/instructor/leave-requests`);
}

export function requestMyLeave(body: { startDate: string; endDate: string; reason?: string }) {
  return request<LeaveRow>(`/api/instructor/leave-requests`, { method: "POST", body: JSON.stringify(body) });
}

/* ---------- Scheduling & fleet (DIQ-909) ---------- */

export type ScheduleRow = {
  id: number;
  learnerId: number | null;
  learnerName: string | null;
  instructorId: number;
  instructorName: string | null;
  vehicleId: number | null;
  vehicleRegistration: string | null;
  sessionDate: string;
  startTime: string;
  endTime: string;
  pickupLocation: string | null;
  status: string;
  notes: string | null;
  sessionSummary: string | null;
  attendance: { status: string } | null;
};

export type VehicleRow = {
  id: number;
  registrationNumber: string;
  type: string;
  transmission: string | null;
  fuelType: string | null;
  status: "active" | "maintenance" | "retired";
  makeModel: string | null;
  year: number | null;
  notes: string | null;
  expiredDocuments?: number;
  expiringDocuments?: number;
};

export function updateSchedule(id: number, body: Record<string, unknown>) {
  return request<ScheduleRow>(`/api/schedules/${id}`, { method: "PATCH", body: JSON.stringify(body) });
}

export function listSchoolLeave(schoolId: number) {
  return request<LeaveRow[]>(`/api/schools/${schoolId}/leave-requests`);
}

export function reviewLeave(id: number, status: "approved" | "rejected") {
  return request<LeaveRow & { clashingSessions: ScheduleRow[] }>(`/api/leave-requests/${id}`, {
    method: "PATCH",
    body: JSON.stringify({ status }),
  });
}

export function createVehicle(schoolId: number, body: Record<string, unknown>) {
  return request<VehicleRow>(`/api/schools/${schoolId}/vehicles`, { method: "POST", body: JSON.stringify(body) });
}

export function updateVehicle(id: number, body: Record<string, unknown>) {
  return request<VehicleRow>(`/api/vehicles/${id}`, { method: "PATCH", body: JSON.stringify(body) });
}

/* ---------- Public trainers (DIQ-911) ---------- */

export type PublicTrainer = {
  id: number;
  name: string;
  photoUrl: string | null;
  yearsExperience: number;
  skills: string[];
  languages: string[];
  womenInstructor: boolean;
  ratingAverage: number;
  ratingCount: number;
  bio: string | null;
};

export function fetchPublicTrainers(slug: string) {
  return request<PublicTrainer[]>(`/api/schools/slug/${encodeURIComponent(slug)}/trainers`);
}
