import { request } from "@/lib/ops-api";

// ── Prospects (DIQ-1103) ──

export type ProspectStage = "new" | "contacted" | "replied" | "claimed" | "lost" | "do_not_contact";
export type ListingType = "school" | "trainer";

export const STAGES: { value: ProspectStage; label: string }[] = [
  { value: "new", label: "New" },
  { value: "contacted", label: "Contacted" },
  { value: "replied", label: "Replied" },
  { value: "claimed", label: "On board" },
  { value: "lost", label: "Lost" },
  { value: "do_not_contact", label: "Do not contact" },
];

export type Prospect = {
  id: number;
  type: ListingType;
  name: string;
  contactPerson: string | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  localityId: number | null;
  localityName: string | null;
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  googlePlaceId: string | null;
  notes: string | null;
  source: "manual" | "csv" | "ads" | "gbp" | "outreach";
  stage: ProspectStage;
  contactable: boolean;
  listing: { id: number; slug: string; status: string } | null;
  ownerAdmin: { id: number; name: string } | null;
  lastContactedAt: string | null;
  createdAt: string;
  updatedAt: string;
};

export type ProspectInput = Partial<{
  type: ListingType;
  name: string;
  contactPerson: string | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  localityId: number | null;
  address: string | null;
  latitude: number | null;
  longitude: number | null;
  googlePlaceId: string | null;
  notes: string | null;
  stage: ProspectStage;
}>;

export type ProspectPage = {
  data: Prospect[];
  meta: { page: number; lastPage: number; total: number; stageCounts: Record<ProspectStage, number> };
};

export function listProspects(params: { search?: string; stage?: string; type?: string; source?: string; page?: number }) {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => {
    if (v !== undefined && v !== "") q.set(k, String(v));
  });
  return request<ProspectPage>(`/api/admin/prospects?${q}`);
}

export function createProspect(body: ProspectInput) {
  return request<Prospect>("/api/admin/prospects", { method: "POST", body: JSON.stringify(body) });
}

export function updateProspect(id: number, body: ProspectInput) {
  return request<Prospect>(`/api/admin/prospects/${id}`, { method: "PATCH", body: JSON.stringify(body) });
}

export function createProspectListing(id: number) {
  return request<Prospect>(`/api/admin/prospects/${id}/listing`, { method: "POST" });
}

export type ImportRow = {
  line: number;
  data: Record<string, string | number | null>;
  status: "new" | "duplicate" | "repeated" | "invalid";
  duplicateOf: { id: number; name: string } | null;
  errors: string[];
  warnings: string[];
};

export type ImportPreview = {
  mapping: Record<string, string>;
  summary: Record<ImportRow["status"], number>;
  rows: ImportRow[];
};

export function importProspects(file: File, type: ListingType, preview: boolean) {
  const form = new FormData();
  form.append("file", file);
  form.append("type", type);
  if (preview) form.append("preview", "1");
  return request<ImportPreview | { created: number; skipped: number }>("/api/admin/prospects/import", { method: "POST", body: form });
}

// ── Claiming a listing (DIQ-1104) ──

export type ClaimPreview = {
  listing: { name: string; type: ListingType; locality: string | null; address: string | null };
  channels: { email?: string; sms?: string };
  expiresAt: string;
};

export function getClaim(token: string) {
  return request<ClaimPreview>(`/api/claims/${encodeURIComponent(token)}`);
}

export function sendClaimCode(token: string, channel: "email" | "sms") {
  return request<{ sentTo: string; expiresAt: string }>(`/api/claims/${encodeURIComponent(token)}/code`, {
    method: "POST",
    body: JSON.stringify({ channel }),
  });
}

export function completeClaim(token: string, body: { code: string; name: string; email: string; password: string }) {
  return request<{ token: string; schoolId: number; listingStatus: string }>(`/api/claims/${encodeURIComponent(token)}/complete`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export function declineClaim(token: string) {
  return request<{ message: string }>(`/api/claims/${encodeURIComponent(token)}/decline`, { method: "POST" });
}

export function issueClaimLink(prospectId: number) {
  return request<{ url: string; expiresAt: string }>(`/api/admin/prospects/${prospectId}/claim-link`, { method: "POST" });
}

// ── Outreach email (DIQ-1105) ──

export type OutreachStep = { subject: string; body: string; delayDays?: number };

export type OutreachCampaign = {
  id: number;
  name: string;
  audience: ListingType;
  status: "draft" | "active" | "paused";
  steps: OutreachStep[];
  startedAt: string | null;
  createdAt: string;
  stats: { enrolled: number; active: number; sent: number; failed: number; clicked: number; claimed: number; unsubscribed: number };
};

export type OutreachOverview = {
  dailyCap: number;
  sentToday: number;
  withinSendingHours: boolean;
  sendingHours: string;
  mailer: string;
  from: string;
  placeholders: string[];
};

export type OutreachLogRow = {
  id: number;
  campaign: string | null;
  prospect: { id: number; name: string; stage: ProspectStage } | null;
  step: number;
  to: string;
  status: "sending" | "sent" | "failed" | "bounced";
  error: string | null;
  clickedAt: string | null;
  sentAt: string;
};

export function getOutreachOverview() {
  return request<OutreachOverview>("/api/admin/outreach");
}

export function listCampaigns() {
  return request<OutreachCampaign[]>("/api/admin/outreach/campaigns");
}

export function saveCampaign(id: number | null, body: Partial<Pick<OutreachCampaign, "name" | "audience" | "steps" | "status">>) {
  return id
    ? request<OutreachCampaign>(`/api/admin/outreach/campaigns/${id}`, { method: "PATCH", body: JSON.stringify(body) })
    : request<OutreachCampaign>("/api/admin/outreach/campaigns", { method: "POST", body: JSON.stringify(body) });
}

export function enrollCampaign(id: number, body: { stages: string[]; localityId?: number; source?: string; dryRun?: boolean }) {
  return request<{ count: number }>(`/api/admin/outreach/campaigns/${id}/enroll`, { method: "POST", body: JSON.stringify(body) });
}

export function sendCampaignTest(id: number, step: number) {
  return request<{ message: string }>(`/api/admin/outreach/campaigns/${id}/test`, { method: "POST", body: JSON.stringify({ step }) });
}

export function listOutreachMessages(params: { campaignId?: number; status?: string; page?: number }) {
  const q = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => v !== undefined && v !== "" && q.set(k, String(v)));
  return request<{ data: OutreachLogRow[]; meta: { page: number; lastPage: number; total: number } }>(`/api/admin/outreach/messages?${q}`);
}

export function markUndeliverable(id: number, reason: "bounced" | "complaint") {
  return request(`/api/admin/outreach/messages/${id}/undeliverable`, { method: "POST", body: JSON.stringify({ reason }) });
}

export function unsubscribe(token: string) {
  return request<{ message: string }>(`/api/outreach/unsubscribe/${encodeURIComponent(token)}`, { method: "POST" });
}
