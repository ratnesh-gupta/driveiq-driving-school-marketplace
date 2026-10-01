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
