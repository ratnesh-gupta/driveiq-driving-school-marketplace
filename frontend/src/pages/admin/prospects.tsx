import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { FileUp, Plus, Store, Target } from "lucide-react";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Skeleton } from "@/components/ui/skeleton";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useListLocalities } from "@/api-client";
import {
  STAGES,
  createProspect,
  createProspectListing,
  importProspects,
  listProspects,
  updateProspect,
  type ImportPreview,
  type ListingType,
  type Prospect,
  type ProspectInput,
  type ProspectStage,
} from "@/lib/acquisition-api";

const SELECT = "h-9 rounded-md border bg-background px-2 text-sm";
const SOURCE_LABEL: Record<Prospect["source"], string> = {
  manual: "Added", csv: "CSV", ads: "Google Ads", gbp: "Google Business", outreach: "Outreach",
};
const STAGE_CLASS: Record<ProspectStage, string> = {
  new: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300",
  contacted: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300",
  replied: "bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300",
  claimed: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  lost: "bg-muted text-muted-foreground",
  do_not_contact: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
};

type FormState = Record<"name" | "contactPerson" | "phone" | "email" | "website" | "address" | "latitude" | "longitude" | "googlePlaceId" | "notes" | "localityId", string> & {
  type: ListingType;
};

function toForm(p: Prospect | null): FormState {
  return {
    type: p?.type ?? "school",
    name: p?.name ?? "",
    contactPerson: p?.contactPerson ?? "",
    phone: p?.phone ?? "",
    email: p?.email ?? "",
    website: p?.website ?? "",
    address: p?.address ?? "",
    latitude: p?.latitude != null ? String(p.latitude) : "",
    longitude: p?.longitude != null ? String(p.longitude) : "",
    googlePlaceId: p?.googlePlaceId ?? "",
    notes: p?.notes ?? "",
    localityId: p?.localityId ? String(p.localityId) : "",
  };
}

function toInput(f: FormState): ProspectInput {
  const text = (v: string) => (v.trim() === "" ? null : v.trim());
  return {
    type: f.type,
    name: f.name.trim(),
    contactPerson: text(f.contactPerson),
    phone: text(f.phone),
    email: text(f.email),
    website: text(f.website),
    address: text(f.address),
    latitude: f.latitude ? Number(f.latitude) : null,
    longitude: f.longitude ? Number(f.longitude) : null,
    googlePlaceId: text(f.googlePlaceId),
    notes: text(f.notes),
    localityId: f.localityId ? Number(f.localityId) : null,
  };
}

/** DIQ-1103: schools and trainers the team is bringing on board. */
export default function AdminProspectsPage() {
  const qc = useQueryClient();
  const { data: localities } = useListLocalities();
  const [stage, setStage] = useState<ProspectStage | "">("");
  const [search, setSearch] = useState("");
  const [type, setType] = useState("");
  const [source, setSource] = useState("");
  const [page, setPage] = useState(1);
  const [editing, setEditing] = useState<Prospect | "new" | null>(null);
  const [form, setForm] = useState<FormState>(toForm(null));
  const [importing, setImporting] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ["admin", "prospects", { stage, search, type, source, page }],
    queryFn: () => listProspects({ stage, search, type, source, page }),
  });
  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "prospects"] });

  const save = useMutation({
    mutationFn: () => (editing === "new" ? createProspect(toInput(form)) : updateProspect((editing as Prospect).id, toInput(form))),
    onSuccess: () => {
      toast.success(editing === "new" ? "Prospect added" : "Prospect saved");
      setEditing(null);
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const setStageOf = useMutation({
    mutationFn: ({ p, next }: { p: Prospect; next: ProspectStage }) => updateProspect(p.id, { stage: next }),
    onSuccess: (_, { next }) => {
      toast.success(next === "do_not_contact" ? "Marked do not contact. Outreach to them is blocked." : "Stage updated");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const listing = useMutation({
    mutationFn: (p: Prospect) => createProspectListing(p.id),
    onSuccess: () => {
      toast.success("Unclaimed listing created. It stays hidden until they claim it.");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const openEdit = (p: Prospect | "new") => {
    setEditing(p);
    setForm(toForm(p === "new" ? null : p));
  };
  const set = (k: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  const counts = data?.meta.stageCounts;
  const all = counts ? Object.values(counts).reduce((a, b) => a + b, 0) : 0;

  return (
    <AdminLayout>
      <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2"><Target className="h-6 w-6" /> Prospects</h1>
          <p className="text-muted-foreground text-sm mt-1">Schools and independent trainers we are bringing on board.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setImporting(true)} data-testid="button-import-prospects"><FileUp className="h-4 w-4 mr-1" /> Import CSV</Button>
          <Button onClick={() => openEdit("new")} data-testid="button-add-prospect"><Plus className="h-4 w-4 mr-1" /> Add prospect</Button>
        </div>
      </div>

      <div className="flex flex-wrap gap-2 mb-4" role="tablist">
        {[{ value: "" as const, label: "All", n: all }, ...STAGES.map((s) => ({ ...s, n: counts?.[s.value] ?? 0 }))].map((s) => (
          <button
            key={s.value || "all"}
            role="tab"
            aria-selected={stage === s.value}
            onClick={() => { setStage(s.value); setPage(1); }}
            className={`rounded-full border px-3 py-1 text-sm ${stage === s.value ? "bg-primary text-primary-foreground border-primary" : "hover:bg-muted"}`}
            data-testid={`tab-stage-${s.value || "all"}`}
          >
            {s.label} <span className="tabular-nums opacity-70">{s.n}</span>
          </button>
        ))}
      </div>

      <div className="flex flex-wrap gap-2 mb-4">
        <Input className="w-64" placeholder="Search name, contact, email or phone" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} data-testid="input-search-prospects" />
        <select className={SELECT} value={type} onChange={(e) => { setType(e.target.value); setPage(1); }} aria-label="Type">
          <option value="">Schools and trainers</option>
          <option value="school">Schools</option>
          <option value="trainer">Independent trainers</option>
        </select>
        <select className={SELECT} value={source} onChange={(e) => { setSource(e.target.value); setPage(1); }} aria-label="Source">
          <option value="">Any source</option>
          {Object.entries(SOURCE_LABEL).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
      </div>

      <div className="rounded-xl border bg-card overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="border-b bg-muted/40">
            <tr>
              {["Prospect", "Contact", "Locality", "Source", "Stage", "Listing", ""].map((h) => (
                <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y">
            {isLoading ? (
              Array.from({ length: 5 }).map((_, i) => <tr key={i}><td colSpan={7} className="px-4 py-3"><Skeleton className="h-8" /></td></tr>)
            ) : !data?.data.length ? (
              <tr><td colSpan={7} className="text-center py-12 text-muted-foreground">No prospects yet. Add one or import a CSV.</td></tr>
            ) : data.data.map((p) => (
              <tr key={p.id} data-testid={`row-prospect-${p.id}`}>
                <td className="px-4 py-3">
                  <div className="font-medium">{p.name}</div>
                  <div className="text-xs text-muted-foreground">{p.type === "trainer" ? "Independent trainer" : "Driving school"}</div>
                </td>
                <td className="px-4 py-3 text-xs">
                  {p.contactPerson && <div>{p.contactPerson}</div>}
                  {p.phone && <div className="tabular-nums">{p.phone}</div>}
                  {p.email && <div className="text-muted-foreground">{p.email}</div>}
                </td>
                <td className="px-4 py-3 text-muted-foreground">{p.localityName ?? "—"}</td>
                <td className="px-4 py-3 text-xs">{SOURCE_LABEL[p.source]}</td>
                <td className="px-4 py-3">
                  {p.stage === "claimed" ? (
                    <Badge className={`border-0 ${STAGE_CLASS.claimed}`}>On board</Badge>
                  ) : (
                    <select
                      className={`${SELECT} h-8 text-xs ${STAGE_CLASS[p.stage]}`}
                      value={p.stage}
                      onChange={(e) => {
                        const next = e.target.value as ProspectStage;
                        if (next === "do_not_contact" && !window.confirm(`Stop all outreach to ${p.name}? Their unclaimed listing is removed.`)) return;
                        setStageOf.mutate({ p, next });
                      }}
                      aria-label={`Stage of ${p.name}`}
                      data-testid={`select-stage-${p.id}`}
                    >
                      {STAGES.filter((s) => s.value !== "claimed").map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                  )}
                </td>
                <td className="px-4 py-3 text-xs">
                  {p.listing ? (
                    <span className="inline-flex items-center gap-1"><Store className="h-3.5 w-3.5" /> {p.listing.status === "unclaimed" ? "Waiting for claim" : p.listing.status}</span>
                  ) : p.contactable ? (
                    <Button size="sm" variant="outline" className="h-7 text-xs" disabled={listing.isPending} onClick={() => listing.mutate(p)} data-testid={`button-create-listing-${p.id}`}>
                      Create listing
                    </Button>
                  ) : "—"}
                </td>
                <td className="px-4 py-3 text-right">
                  <Button size="sm" variant="ghost" className="h-7 text-xs" onClick={() => openEdit(p)}>Edit</Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {data && data.meta.lastPage > 1 && (
          <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
            <span className="text-muted-foreground">Page {data.meta.page} of {data.meta.lastPage} · {data.meta.total} prospects</span>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((n) => n - 1)}>Previous</Button>
              <Button size="sm" variant="outline" disabled={page >= data.meta.lastPage} onClick={() => setPage((n) => n + 1)}>Next</Button>
            </div>
          </div>
        )}
      </div>

      <Dialog open={!!editing} onOpenChange={(o) => !o && setEditing(null)}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editing === "new" ? "Add prospect" : `Edit ${form.name}`}</DialogTitle>
            <DialogDescription>Only the name is required. A phone or email is needed to reach them.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1">
              <Label htmlFor="p-type">Type</Label>
              <select id="p-type" className={`${SELECT} w-full`} value={form.type} onChange={set("type")}>
                <option value="school">Driving school</option>
                <option value="trainer">Independent trainer</option>
              </select>
            </div>
            <div className="space-y-1"><Label htmlFor="p-name">Name</Label><Input id="p-name" value={form.name} onChange={set("name")} data-testid="input-prospect-name" /></div>
            <div className="space-y-1"><Label htmlFor="p-contact">Contact person</Label><Input id="p-contact" value={form.contactPerson} onChange={set("contactPerson")} /></div>
            <div className="space-y-1"><Label htmlFor="p-phone">Phone</Label><Input id="p-phone" value={form.phone} onChange={set("phone")} data-testid="input-prospect-phone" /></div>
            <div className="space-y-1"><Label htmlFor="p-email">Email</Label><Input id="p-email" type="email" value={form.email} onChange={set("email")} data-testid="input-prospect-email" /></div>
            <div className="space-y-1"><Label htmlFor="p-web">Website</Label><Input id="p-web" value={form.website} onChange={set("website")} /></div>
            <div className="space-y-1">
              <Label htmlFor="p-locality">Locality</Label>
              <select id="p-locality" className={`${SELECT} w-full`} value={form.localityId} onChange={set("localityId")}>
                <option value="">—</option>
                {(localities ?? []).map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
              </select>
            </div>
            <div className="space-y-1"><Label htmlFor="p-place">Google place ID</Label><Input id="p-place" value={form.googlePlaceId} onChange={set("googlePlaceId")} /></div>
            <div className="space-y-1 sm:col-span-2"><Label htmlFor="p-address">Address</Label><Input id="p-address" value={form.address} onChange={set("address")} /></div>
            <div className="space-y-1"><Label htmlFor="p-lat">Latitude</Label><Input id="p-lat" value={form.latitude} onChange={set("latitude")} /></div>
            <div className="space-y-1"><Label htmlFor="p-lng">Longitude</Label><Input id="p-lng" value={form.longitude} onChange={set("longitude")} /></div>
            <div className="space-y-1 sm:col-span-2">
              <Label htmlFor="p-notes">Notes</Label>
              <Textarea id="p-notes" rows={3} value={form.notes} onChange={set("notes")} placeholder="Where you found them, what they said, when to call back" />
              <p className="text-[11px] text-muted-foreground">Business details only. Keep notes about people factual: they can ask to see them.</p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditing(null)}>Cancel</Button>
            <Button disabled={!form.name.trim() || save.isPending} onClick={() => save.mutate()} data-testid="button-save-prospect">Save</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <ImportDialog open={importing} onClose={() => setImporting(false)} onImported={refresh} />
    </AdminLayout>
  );
}

const ROW_STATUS: Record<string, string> = {
  new: "Will be added",
  duplicate: "Already on the list",
  repeated: "Repeated in this file",
  invalid: "Cannot be added",
};

function ImportDialog({ open, onClose, onImported }: { open: boolean; onClose: () => void; onImported: () => void }) {
  const [file, setFile] = useState<File | null>(null);
  const [type, setType] = useState<ListingType>("school");
  const [preview, setPreview] = useState<ImportPreview | null>(null);

  const reset = () => {
    setFile(null);
    setPreview(null);
  };

  const check = useMutation({
    mutationFn: () => importProspects(file!, type, true) as Promise<ImportPreview>,
    onSuccess: setPreview,
    onError: (e: Error) => toast.error(e.message),
  });
  const run = useMutation({
    mutationFn: () => importProspects(file!, type, false) as Promise<{ created: number; skipped: number }>,
    onSuccess: (r) => {
      toast.success(`${r.created} prospects added${r.skipped ? `, ${r.skipped} skipped` : ""}`);
      onImported();
      reset();
      onClose();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!o) { reset(); onClose(); } }}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Import prospects from CSV</DialogTitle>
          <DialogDescription>
            Columns are matched by their heading: name (required), type, contact person, phone, email, website, locality,
            address, latitude, longitude, place id, notes. Nothing is saved until you confirm.
          </DialogDescription>
        </DialogHeader>
        <div className="flex flex-wrap items-end gap-3">
          <div className="space-y-1">
            <Label htmlFor="import-file">CSV file (up to 2,000 rows)</Label>
            <Input id="import-file" type="file" accept=".csv,text/csv" onChange={(e) => { setFile(e.target.files?.[0] ?? null); setPreview(null); }} data-testid="input-import-file" />
          </div>
          <div className="space-y-1">
            <Label htmlFor="import-type">Rows without a type are</Label>
            <select id="import-type" className={SELECT} value={type} onChange={(e) => { setType(e.target.value as ListingType); setPreview(null); }}>
              <option value="school">Driving schools</option>
              <option value="trainer">Independent trainers</option>
            </select>
          </div>
          <Button variant="outline" disabled={!file || check.isPending} onClick={() => check.mutate()} data-testid="button-preview-import">Check file</Button>
        </div>

        {preview && (
          <div className="space-y-3 text-sm">
            <p className="text-muted-foreground">
              Columns used: {Object.entries(preview.mapping).map(([h, f]) => `${h} → ${f.replace("_", " ")}`).join(", ")}
            </p>
            <p data-testid="import-summary">
              <strong>{preview.summary.new}</strong> new · {preview.summary.duplicate} already on the list · {preview.summary.repeated} repeated · {preview.summary.invalid} with errors
            </p>
            <div className="max-h-72 overflow-y-auto rounded border">
              <table className="w-full text-xs">
                <thead className="bg-muted/40 sticky top-0">
                  <tr><th className="text-left p-2">Line</th><th className="text-left p-2">Name</th><th className="text-left p-2">Phone</th><th className="text-left p-2">Result</th></tr>
                </thead>
                <tbody className="divide-y">
                  {preview.rows.map((r) => (
                    <tr key={r.line} className={r.status === "new" ? "" : "text-muted-foreground"}>
                      <td className="p-2 tabular-nums">{r.line}</td>
                      <td className="p-2">{String(r.data.name ?? "—")}</td>
                      <td className="p-2">{String(r.data.phone ?? "")}</td>
                      <td className="p-2">
                        {ROW_STATUS[r.status]}
                        {r.duplicateOf && ` (${r.duplicateOf.name})`}
                        {[...r.errors, ...r.warnings].map((m) => <div key={m} className={r.errors.includes(m) ? "text-destructive" : "text-amber-600"}>{m}</div>)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={() => { reset(); onClose(); }}>Cancel</Button>
          <Button disabled={!preview || !preview.summary.new || run.isPending} onClick={() => run.mutate()} data-testid="button-run-import">
            Add {preview?.summary.new ?? 0} prospects
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
