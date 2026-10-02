import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { AlertTriangle, Pause, Play, Plus, Send, UserPlus } from "lucide-react";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useListLocalities } from "@/api-client";
import {
  enrollCampaign,
  getOutreachOverview,
  listCampaigns,
  listOutreachMessages,
  markUndeliverable,
  saveCampaign,
  sendCampaignTest,
  type ListingType,
  type OutreachCampaign,
  type OutreachLanguage,
  type OutreachPresets,
  type OutreachStep,
} from "@/lib/acquisition-api";

const SELECT = "h-9 rounded-md border bg-background px-2 text-sm";

const STATUS_CLASS: Record<OutreachCampaign["status"], string> = {
  draft: "bg-muted text-muted-foreground",
  active: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  paused: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300",
};

/** DIQ-1105: outreach email campaigns to prospects. */
export default function AdminOutreachPage() {
  const qc = useQueryClient();
  const overview = useQuery({ queryKey: ["admin", "outreach", "overview"], queryFn: getOutreachOverview });
  const campaigns = useQuery({ queryKey: ["admin", "outreach", "campaigns"], queryFn: listCampaigns });
  const [editing, setEditing] = useState<OutreachCampaign | "new" | null>(null);
  const [enrolling, setEnrolling] = useState<OutreachCampaign | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: ["admin", "outreach"] });

  const setStatus = useMutation({
    mutationFn: ({ c, status }: { c: OutreachCampaign; status: OutreachCampaign["status"] }) => saveCampaign(c.id, { status }),
    onSuccess: (c) => {
      toast.success(c.status === "active" ? "Campaign running. Emails go out in sending hours." : "Campaign paused");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const test = useMutation({
    mutationFn: ({ c, step }: { c: OutreachCampaign; step: number }) => sendCampaignTest(c.id, step),
    onSuccess: (r) => toast.success(r.message),
    onError: (e: Error) => toast.error(e.message),
  });

  const o = overview.data;

  return (
    <AdminLayout>
      <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold flex items-center gap-2"><Send className="h-6 w-6" /> Outreach email</h1>
          <p className="text-muted-foreground text-sm mt-1">Short email sequences inviting prospects to claim their free listing.</p>
        </div>
        <Button onClick={() => setEditing("new")} data-testid="button-new-campaign"><Plus className="h-4 w-4 mr-1" /> New campaign</Button>
      </div>

      {o && (
        <div className="grid sm:grid-cols-3 gap-3 mb-6 text-sm">
          <div className="rounded-xl border bg-card p-4">
            <div className="text-muted-foreground">Sent today</div>
            <div className="text-xl font-bold tabular-nums" data-testid="text-sent-today">{o.sentToday} / {o.dailyCap}</div>
          </div>
          <div className="rounded-xl border bg-card p-4">
            <div className="text-muted-foreground">Sending hours</div>
            <div className="font-medium">{o.sendingHours}</div>
            <div className="text-xs text-muted-foreground">{o.withinSendingHours ? "Sending now" : "Paused until the next window"}</div>
          </div>
          <div className="rounded-xl border bg-card p-4">
            <div className="text-muted-foreground">From</div>
            <div className="font-medium break-all">{o.from}</div>
          </div>
          {o.mailer === "log" && (
            <div className="sm:col-span-3 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/20 p-3 flex gap-2" data-testid="banner-log-mailer">
              <AlertTriangle className="h-4 w-4 mt-0.5 text-amber-600" />
              Outreach email is going to the server log, not to people. Set OUTREACH_MAILER=outreach and the OUTREACH_MAIL_* settings to send for real.
            </div>
          )}
        </div>
      )}

      <Tabs defaultValue="campaigns">
        <TabsList>
          <TabsTrigger value="campaigns">Campaigns</TabsTrigger>
          <TabsTrigger value="log">Emails sent</TabsTrigger>
        </TabsList>

        <TabsContent value="campaigns" className="space-y-3 pt-2">
          {campaigns.isLoading ? (
            <Skeleton className="h-32" />
          ) : !campaigns.data?.length ? (
            <p className="text-sm text-muted-foreground py-8 text-center">No campaigns yet. Create one, add prospects, send yourself a test, then start it.</p>
          ) : campaigns.data.map((c) => (
            <div key={c.id} className="rounded-xl border bg-card p-4" data-testid={`campaign-${c.id}`}>
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <div className="font-semibold flex items-center gap-2">
                    {c.name}
                    <Badge className={`border-0 ${STATUS_CLASS[c.status]}`}>{c.status}</Badge>
                  </div>
                  <div className="text-xs text-muted-foreground">
                    {c.audience === "trainer" ? "Independent trainers" : "Driving schools"} · {LANGUAGE_LABEL[c.language]} · {c.steps.length} email{c.steps.length > 1 ? "s" : ""}
                  </div>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" onClick={() => setEnrolling(c)} data-testid={`button-enroll-${c.id}`}><UserPlus className="h-4 w-4 mr-1" /> Add prospects</Button>
                  <Button size="sm" variant="outline" disabled={test.isPending} onClick={() => test.mutate({ c, step: 0 })} data-testid={`button-test-${c.id}`}>Send me a test</Button>
                  <Button size="sm" variant="ghost" onClick={() => setEditing(c)}>Edit</Button>
                  {c.status === "active" ? (
                    <Button size="sm" variant="outline" onClick={() => setStatus.mutate({ c, status: "paused" })}><Pause className="h-4 w-4 mr-1" /> Pause</Button>
                  ) : (
                    <Button size="sm" disabled={!c.stats.enrolled} onClick={() => setStatus.mutate({ c, status: "active" })} data-testid={`button-start-${c.id}`}>
                      <Play className="h-4 w-4 mr-1" /> {c.status === "paused" ? "Resume" : "Start"}
                    </Button>
                  )}
                </div>
              </div>
              <div className="mt-3 grid grid-cols-3 sm:grid-cols-6 gap-2 text-center text-xs">
                {([
                  ["Added", c.stats.enrolled],
                  ["Emails sent", c.stats.sent],
                  ["Opened link", c.stats.clicked],
                  ["Claimed", c.stats.claimed],
                  ["Unsubscribed", c.stats.unsubscribed],
                  ["Failed", c.stats.failed],
                ] as const).map(([label, n]) => (
                  <div key={label} className="rounded-lg bg-muted/40 p-2">
                    <div className="text-base font-semibold tabular-nums">{n}</div>
                    <div className="text-muted-foreground">{label}</div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </TabsContent>

        <TabsContent value="log" className="pt-2">
          <OutreachLog />
        </TabsContent>
      </Tabs>

      <CampaignDialog campaign={editing} placeholders={o?.placeholders ?? []} presets={o?.presets} onClose={() => setEditing(null)} onSaved={refresh} />
      <EnrollDialog campaign={enrolling} onClose={() => setEnrolling(null)} onDone={refresh} />
    </AdminLayout>
  );
}

const LANGUAGE_LABEL: Record<OutreachLanguage, string> = { en: "English", hi: "हिन्दी (Hindi)", mr: "मराठी (Marathi)" };

function CampaignDialog({ campaign, placeholders, presets, onClose, onSaved }: {
  campaign: OutreachCampaign | "new" | null;
  placeholders: string[];
  presets?: OutreachPresets;
  onClose: () => void;
  onSaved: () => void;
}) {
  const isNew = campaign === "new";
  const [name, setName] = useState("");
  const [audience, setAudience] = useState<ListingType>("school");
  const [language, setLanguage] = useState<OutreachLanguage>("en");
  const [steps, setSteps] = useState<OutreachStep[]>([]);
  const [loadedFor, setLoadedFor] = useState<unknown>(null);
  const preset = (a: ListingType, l: OutreachLanguage) => presets?.[a]?.[l] ?? [];

  if (campaign && loadedFor !== campaign && (!isNew || presets)) {
    setLoadedFor(campaign);
    setName(isNew ? "" : campaign.name);
    setAudience(isNew ? "school" : campaign.audience);
    setLanguage(isNew ? "en" : campaign.language);
    setSteps(isNew ? preset("school", "en") : campaign.steps);
  }

  const save = useMutation({
    mutationFn: () => saveCampaign(isNew ? null : (campaign as OutreachCampaign).id, { name, audience, language, steps }),
    onSuccess: () => {
      toast.success("Campaign saved");
      onSaved();
      onClose();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const setStep = (i: number, patch: Partial<OutreachStep>) => setSteps((s) => s.map((st, j) => (j === i ? { ...st, ...patch } : st)));
  const missingLink = steps.some((s) => !s.body.includes("{{link}}"));

  return (
    <Dialog open={!!campaign} onOpenChange={(o) => { if (!o) { setLoadedFor(null); onClose(); } }}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isNew ? "New campaign" : "Edit campaign"}</DialogTitle>
          <DialogDescription>
            Placeholders: {placeholders.map((p) => `{{${p}}}`).join(", ")}. {"{{link}}"} is their claim link (or the sign-up page when
            they have no listing yet). Every email gets an unsubscribe link and your postal address.
          </DialogDescription>
        </DialogHeader>
        <div className="grid sm:grid-cols-2 gap-3">
          <div className="space-y-1"><Label htmlFor="c-name">Name</Label><Input id="c-name" value={name} onChange={(e) => setName(e.target.value)} data-testid="input-campaign-name" /></div>
          <div className="space-y-1">
            <Label htmlFor="c-aud">Who</Label>
            <select
              id="c-aud"
              className={`${SELECT} w-full`}
              value={audience}
              onChange={(e) => {
                const next = e.target.value as ListingType;
                setAudience(next);
                if (isNew) setSteps(preset(next, language));
              }}
            >
              <option value="school">Driving schools</option>
              <option value="trainer">Independent trainers</option>
            </select>
          </div>
          <div className="space-y-1">
            <Label htmlFor="c-lang">Language</Label>
            <select
              id="c-lang"
              className={`${SELECT} w-full`}
              value={language}
              onChange={(e) => {
                const next = e.target.value as OutreachLanguage;
                setLanguage(next);
                if (isNew) setSteps(preset(audience, next));
              }}
              data-testid="select-campaign-language"
            >
              {(Object.keys(LANGUAGE_LABEL) as OutreachLanguage[]).map((l) => <option key={l} value={l}>{LANGUAGE_LABEL[l]}</option>)}
            </select>
          </div>
          <div className="space-y-1 flex items-end">
            <Button
              type="button"
              variant="outline"
              className="w-full"
              disabled={!presets}
              onClick={() => window.confirm("Replace the emails below with our ready-made sequence?") && setSteps(preset(audience, language))}
              data-testid="button-use-preset"
            >
              Start from our ready-made emails
            </Button>
          </div>
        </div>
        <div className="space-y-4">
          {steps.map((s, i) => (
            <div key={i} className="rounded-lg border p-3 space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">Email {i + 1}</span>
                <div className="flex items-center gap-2">
                  {i > 0 && (
                    <label className="text-xs flex items-center gap-1">
                      days after the previous
                      <Input type="number" min={1} max={30} className="w-16 h-8" value={s.delayDays ?? 4} onChange={(e) => setStep(i, { delayDays: Number(e.target.value) })} />
                    </label>
                  )}
                  {i > 0 && <Button size="sm" variant="ghost" className="text-destructive h-8" onClick={() => setSteps((st) => st.filter((_, j) => j !== i))}>Remove</Button>}
                </div>
              </div>
              <Input aria-label={`Subject of email ${i + 1}`} value={s.subject} onChange={(e) => setStep(i, { subject: e.target.value })} />
              <Textarea aria-label={`Body of email ${i + 1}`} rows={8} value={s.body} onChange={(e) => setStep(i, { body: e.target.value })} />
            </div>
          ))}
          {steps.length < 3 && (
            <Button size="sm" variant="outline" onClick={() => setSteps((s) => [...s, { subject: "Following up", body: "Hi {{contact}},\n\n{{link}}", delayDays: 4 }])}>
              <Plus className="h-4 w-4 mr-1" /> Add a follow-up
            </Button>
          )}
          {missingLink && <p className="text-xs text-amber-600">An email without {"{{link}}"} gives them nothing to click.</p>}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Cancel</Button>
          <Button disabled={!name.trim() || !steps.length || save.isPending} onClick={() => save.mutate()} data-testid="button-save-campaign">Save</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function EnrollDialog({ campaign, onClose, onDone }: { campaign: OutreachCampaign | null; onClose: () => void; onDone: () => void }) {
  const { data: localities } = useListLocalities();
  const [stages, setStages] = useState<string[]>(["new"]);
  const [localityId, setLocalityId] = useState("");
  const [source, setSource] = useState("");
  const [count, setCount] = useState<number | null>(null);

  const body = (dryRun: boolean) => ({ stages, dryRun, ...(localityId ? { localityId: Number(localityId) } : {}), ...(source ? { source } : {}) });
  const check = useMutation({ mutationFn: () => enrollCampaign(campaign!.id, body(true)), onSuccess: (r) => setCount(r.count) });
  const run = useMutation({
    mutationFn: () => enrollCampaign(campaign!.id, body(false)),
    onSuccess: (r) => {
      toast.success(`${r.count} prospects added`);
      onDone();
      setCount(null);
      onClose();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <Dialog open={!!campaign} onOpenChange={(o) => { if (!o) { setCount(null); onClose(); } }}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add prospects to {campaign?.name}</DialogTitle>
          <DialogDescription>
            Only {campaign?.audience === "trainer" ? "independent trainers" : "driving schools"} with an email who are not already in this
            campaign and have not opted out.
          </DialogDescription>
        </DialogHeader>
        <div className="space-y-3 text-sm">
          <div className="flex gap-4">
            {["new", "contacted"].map((s) => (
              <label key={s} className="flex items-center gap-2">
                <Checkbox
                  checked={stages.includes(s)}
                  onCheckedChange={(v) => { setCount(null); setStages((st) => (v ? [...st, s] : st.filter((x) => x !== s))); }}
                />
                {s === "new" ? "New" : "Already contacted"}
              </label>
            ))}
          </div>
          <select className={`${SELECT} w-full`} value={localityId} onChange={(e) => { setLocalityId(e.target.value); setCount(null); }} aria-label="Locality">
            <option value="">All localities</option>
            {(localities ?? []).map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
          </select>
          <select className={`${SELECT} w-full`} value={source} onChange={(e) => { setSource(e.target.value); setCount(null); }} aria-label="Source">
            <option value="">Any source</option>
            <option value="manual">Added by hand</option>
            <option value="csv">CSV import</option>
            <option value="ads">Google Ads</option>
          </select>
          {count !== null && <p data-testid="text-enroll-count"><strong>{count}</strong> prospects match.</p>}
        </div>
        <DialogFooter>
          <Button variant="outline" disabled={!stages.length || check.isPending} onClick={() => check.mutate()} data-testid="button-enroll-check">Check</Button>
          <Button disabled={!count || run.isPending} onClick={() => run.mutate()} data-testid="button-enroll-run">Add {count ?? ""}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function OutreachLog() {
  const qc = useQueryClient();
  const [page, setPage] = useState(1);
  const { data, isLoading } = useQuery({ queryKey: ["admin", "outreach", "log", page], queryFn: () => listOutreachMessages({ page }) });
  const mark = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: "bounced" | "complaint" }) => markUndeliverable(id, reason),
    onSuccess: () => {
      toast.success("Address blocked for outreach");
      void qc.invalidateQueries({ queryKey: ["admin", "outreach"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="rounded-xl border bg-card overflow-x-auto">
      <table className="w-full text-sm">
        <thead className="border-b bg-muted/40">
          <tr>{["When", "To", "Prospect", "Campaign", "Status", ""].map((h) => <th key={h} className="text-left px-3 py-2 text-xs font-semibold text-muted-foreground">{h}</th>)}</tr>
        </thead>
        <tbody className="divide-y">
          {isLoading ? (
            <tr><td colSpan={6} className="p-3"><Skeleton className="h-8" /></td></tr>
          ) : !data?.data.length ? (
            <tr><td colSpan={6} className="text-center py-8 text-muted-foreground">Nothing sent yet.</td></tr>
          ) : data.data.map((m) => (
            <tr key={m.id}>
              <td className="px-3 py-2 text-xs whitespace-nowrap">{new Date(m.sentAt).toLocaleString()}</td>
              <td className="px-3 py-2 text-xs">{m.to}</td>
              <td className="px-3 py-2">{m.prospect?.name ?? "—"}</td>
              <td className="px-3 py-2 text-xs">{m.campaign} · email {m.step}</td>
              <td className="px-3 py-2 text-xs">
                {m.status}{m.clickedAt ? " · opened link" : ""}
                {m.error && <div className="text-destructive truncate max-w-48" title={m.error}>{m.error}</div>}
              </td>
              <td className="px-3 py-2 text-right whitespace-nowrap">
                {m.status === "sent" && (
                  <>
                    <Button size="sm" variant="ghost" className="h-7 text-xs" onClick={() => mark.mutate({ id: m.id, reason: "bounced" })}>Bounced</Button>
                    <Button size="sm" variant="ghost" className="h-7 text-xs" onClick={() => mark.mutate({ id: m.id, reason: "complaint" })}>Spam complaint</Button>
                  </>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
      {data && data.meta.lastPage > 1 && (
        <div className="flex justify-end gap-2 border-t p-2">
          <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
          <Button size="sm" variant="outline" disabled={page >= data.meta.lastPage} onClick={() => setPage((p) => p + 1)}>Next</Button>
        </div>
      )}
    </div>
  );
}
