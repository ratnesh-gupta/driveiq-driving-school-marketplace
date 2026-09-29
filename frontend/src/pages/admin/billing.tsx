import { useEffect, useState } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useListLocalities } from "@/api-client";
import {
  assignSubscription,
  cancelSubscription,
  createPlacement,
  endPlacement,
  fetchMarketplaceSettings,
  fetchMonetizationOverview,
  listAdminInvoices,
  listAdminSubscriptions,
  listPlacements,
  listSchoolsPage,
  recordInvoicePayment,
  saveMarketplaceSettings,
  voidInvoice,
  type PlacementRow,
} from "@/lib/ops-api";
import { PLAN_LABEL, formatInr } from "@/lib/plan";

const SELECT = "h-9 rounded-md border bg-background px-3 text-sm";
const date = (iso: string | null | undefined) => (iso ? new Date(iso).toLocaleDateString() : "—");

function Stat({ label, value, sub }: { label: string; value: string | number; sub?: string }) {
  return (
    <div className="rounded-xl border bg-card p-4">
      <div className="text-xs text-muted-foreground">{label}</div>
      <div className="text-2xl font-bold mt-1">{value}</div>
      {sub && <div className="text-xs text-muted-foreground mt-0.5">{sub}</div>}
    </div>
  );
}

function OverviewTab() {
  const { data: o, isLoading } = useQuery({ queryKey: ["admin", "monetization"], queryFn: fetchMonetizationOverview });
  if (isLoading || !o) return <Skeleton className="h-48 rounded-xl" />;

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <Stat label="MRR" value={formatInr(o.mrr)} sub={`ARR ${formatInr(o.arr)}`} />
        <Stat label="Paying schools" value={o.activePaid} sub={Object.entries(o.byTier).map(([k, v]) => `${PLAN_LABEL[k] ?? k} ${v}`).join(" · ") || "—"} />
        <Stat label="Churn (30 days)" value={`${Math.round(o.churn30d.rate * 100)}%`} sub={`${o.churn30d.churned} of ${o.churn30d.paidAtStart} paying`} />
        <Stat label="Open trials" value={o.trials} />
        <Stat label="Sponsored slots used" value={`${Math.round(o.sponsoredSlots.utilization * 100)}%`} sub={`${o.sponsoredSlots.eligibleSchools} eligible · ${o.sponsoredSlots.perPage} slots/page`} />
        <Stat label="Unpaid invoices" value={o.pendingInvoices.count} sub={formatInr(o.pendingInvoices.total)} />
      </div>
      <div className="grid md:grid-cols-2 gap-4">
        {[
          { title: "Paid plans ending in 14 days", rows: o.expiringSoon },
          { title: "Trials ending in 7 days", rows: o.trialsEndingSoon },
        ].map(({ title, rows }) => (
          <div key={title} className="rounded-xl border bg-card">
            <div className="px-4 py-3 border-b font-semibold text-sm">{title}</div>
            {!rows.length ? (
              <div className="px-4 py-6 text-sm text-muted-foreground">None</div>
            ) : (
              <ul className="divide-y text-sm">
                {rows.map((r) => (
                  <li key={r.id} className="px-4 py-2 flex justify-between">
                    <span>{r.schoolName} · {PLAN_LABEL[r.planCode ?? ""] ?? r.planCode}</span>
                    <span className="text-muted-foreground">{date(r.expiresAt)}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

function InvoicesTab() {
  const qc = useQueryClient();
  const [status, setStatus] = useState("issued");
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "invoices", status],
    queryFn: () => listAdminInvoices(status || undefined),
    placeholderData: keepPreviousData,
  });

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: ["admin", "invoices"] });
    void qc.invalidateQueries({ queryKey: ["admin", "monetization"] });
  };

  const pay = useMutation({
    mutationFn: ({ id, reference }: { id: number; reference: string }) => recordInvoicePayment(id, { reference }),
    onSuccess: (inv) => {
      toast.success(`${inv.invoiceNumber} paid — ${PLAN_LABEL[inv.planCode]} activated`);
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const cancel = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason?: string }) => voidInvoice(id, reason),
    onSuccess: () => {
      toast.success("Invoice voided");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div>
      <select className={`${SELECT} mb-4`} value={status} onChange={(e) => setStatus(e.target.value)} aria-label="Invoice status">
        <option value="issued">Unpaid</option>
        <option value="paid">Paid</option>
        <option value="void">Void</option>
        <option value="">All</option>
      </select>
      {isLoading ? (
        <Skeleton className="h-32 rounded-xl" />
      ) : !data?.data.length ? (
        <div className="py-10 text-center text-sm text-muted-foreground rounded-xl border bg-card">No invoices.</div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.data.map((inv) => (
            <div key={inv.id} className="px-4 py-3 flex flex-wrap items-center gap-3 text-sm" data-testid={`admin-invoice-${inv.id}`}>
              <div className="flex-1 min-w-[220px]">
                <div className="font-medium">{inv.invoiceNumber} · {inv.schoolName}</div>
                <div className="text-xs text-muted-foreground">
                  {PLAN_LABEL[inv.planCode]} × {inv.months} mo · {formatInr(inv.total)} · issued {date(inv.issuedAt)}
                  {inv.billedToGstin ? ` · GSTIN ${inv.billedToGstin}` : ""}
                  {inv.paymentReference ? ` · ref ${inv.paymentReference}` : ""}
                </div>
              </div>
              <span className="text-xs capitalize text-muted-foreground">{inv.status}</span>
              {inv.status === "issued" && (
                <>
                  <Button
                    size="sm"
                    disabled={pay.isPending}
                    onClick={() => {
                      const reference = window.prompt(`UTR / transaction reference for ${formatInr(inv.total)}`);
                      if (reference?.trim()) pay.mutate({ id: inv.id, reference: reference.trim() });
                    }}
                  >
                    Record payment
                  </Button>
                  <Button
                    size="sm"
                    variant="ghost"
                    disabled={cancel.isPending}
                    onClick={() => {
                      const reason = window.prompt("Reason for voiding (optional)");
                      if (reason !== null) cancel.mutate({ id: inv.id, reason: reason || undefined });
                    }}
                  >
                    Void
                  </Button>
                </>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function SubscriptionsTab() {
  const qc = useQueryClient();
  const [status, setStatus] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "subscriptions", { status, search, page }],
    queryFn: () => listAdminSubscriptions({ status, search, page }),
    placeholderData: keepPreviousData,
  });

  const [schoolId, setSchoolId] = useState("");
  const [planCode, setPlanCode] = useState("featured");
  const [months, setMonths] = useState(1);

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: ["admin", "subscriptions"] });
    void qc.invalidateQueries({ queryKey: ["admin", "monetization"] });
  };
  const assign = useMutation({
    mutationFn: () => assignSubscription({ schoolId: Number(schoolId), planCode, months, notes: "Assigned by admin" }),
    onSuccess: () => {
      toast.success("Plan assigned");
      setSchoolId("");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const cancel = useMutation({
    mutationFn: (id: number) => cancelSubscription(id),
    onSuccess: () => {
      toast.success("Plan cancelled");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="space-y-4">
      <div className="rounded-xl border bg-card p-4 flex flex-wrap items-end gap-3">
        <div>
          <Label htmlFor="assign-school">School ID</Label>
          <Input id="assign-school" className="mt-1 w-28" inputMode="numeric" value={schoolId} onChange={(e) => setSchoolId(e.target.value.replace(/\D/g, ""))} />
        </div>
        <div>
          <Label htmlFor="assign-plan">Plan</Label>
          <select id="assign-plan" className={`${SELECT} mt-1 block`} value={planCode} onChange={(e) => setPlanCode(e.target.value)}>
            {["basic", "featured", "premium", "enterprise"].map((c) => <option key={c} value={c}>{PLAN_LABEL[c]}</option>)}
          </select>
        </div>
        <div>
          <Label htmlFor="assign-months">Months</Label>
          <select id="assign-months" className={`${SELECT} mt-1 block`} value={months} onChange={(e) => setMonths(Number(e.target.value))}>
            {[1, 3, 6, 12].map((m) => <option key={m} value={m}>{m}</option>)}
          </select>
        </div>
        <Button disabled={!schoolId || assign.isPending} onClick={() => assign.mutate()}>Assign without invoice</Button>
        <p className="text-xs text-muted-foreground basis-full">For comps and corrections. Paid upgrades go through invoices so there is a payment record.</p>
      </div>

      <div className="flex flex-wrap gap-2">
        <Input className="max-w-xs" placeholder="Search school" value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} aria-label="Search school" />
        <select className={SELECT} value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} aria-label="Subscription status">
          <option value="">Any status</option>
          {["active", "trial", "cancelled", "expired"].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      {isLoading ? (
        <Skeleton className="h-40 rounded-xl" />
      ) : (
        <div className="rounded-xl border bg-card overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b bg-muted/40">
              <tr>
                {["School", "Plan", "Status", "Starts", "Ends", ""].map((h) => (
                  <th key={h} className="text-left px-4 py-2 text-xs font-semibold text-muted-foreground">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y">
              {data?.data.map((s) => (
                <tr key={s.id}>
                  <td className="px-4 py-2">{s.schoolName} <span className="text-xs text-muted-foreground">#{s.schoolId}</span></td>
                  <td className="px-4 py-2">{PLAN_LABEL[s.planCode ?? ""] ?? s.planCode}</td>
                  <td className="px-4 py-2 capitalize">{s.status}{s.current ? "" : " (ended)"}</td>
                  <td className="px-4 py-2">{date(s.startsAt)}</td>
                  <td className="px-4 py-2">{date(s.expiresAt)}</td>
                  <td className="px-4 py-2 text-right">
                    {s.status === "active" && s.current && (
                      <Button size="sm" variant="ghost" disabled={cancel.isPending} onClick={() => window.confirm(`Cancel ${s.schoolName}'s plan now?`) && cancel.mutate(s.schoolId)}>
                        Cancel
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {data && data.meta.lastPage > 1 && (
        <div className="flex gap-2 justify-end">
          <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
          <Button size="sm" variant="outline" disabled={page >= data.meta.lastPage} onClick={() => setPage((p) => p + 1)}>Next</Button>
        </div>
      )}
    </div>
  );
}

function PlacementsTab() {
  const qc = useQueryClient();
  const { data: placements, isLoading } = useQuery({ queryKey: ["admin", "placements"], queryFn: listPlacements });
  const { data: settings } = useQuery({ queryKey: ["admin", "marketplace-settings"], queryFn: fetchMarketplaceSettings });
  const { data: schools } = useQuery({
    queryKey: ["admin", "school-options"],
    queryFn: () => listSchoolsPage<{ id: number; name: string }>({ limit: 100, offset: 0 }),
  });
  const { data: localities } = useListLocalities();

  const [schoolId, setSchoolId] = useState("");
  const [placement, setPlacement] = useState<PlacementRow["placement"]>("search_top");
  const [localityId, setLocalityId] = useState("");
  const [days, setDays] = useState(30);
  const [slots, setSlots] = useState(2);
  const [homepage, setHomepage] = useState(6);

  useEffect(() => {
    if (settings) {
      setSlots(settings.sponsored_slots_per_page);
      setHomepage(settings.homepage_slots);
    }
  }, [settings]);

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: ["admin", "placements"] });
    void qc.invalidateQueries({ queryKey: ["admin", "monetization"] });
  };
  const create = useMutation({
    mutationFn: () =>
      createPlacement({
        schoolId: Number(schoolId),
        placement,
        ...(placement === "locality" ? { localityId: Number(localityId) } : {}),
        startsAt: new Date().toISOString(),
        endsAt: new Date(Date.now() + days * 86_400_000).toISOString(),
      }),
    onSuccess: () => {
      toast.success("Campaign started");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const end = useMutation({ mutationFn: endPlacement, onSuccess: refresh, onError: (e: Error) => toast.error(e.message) });
  const saveSlots = useMutation({
    mutationFn: () => saveMarketplaceSettings({ sponsored_slots_per_page: slots, homepage_slots: homepage }),
    onSuccess: () => {
      toast.success("Slots saved");
      void qc.invalidateQueries({ queryKey: ["admin", "marketplace-settings"] });
      void qc.invalidateQueries({ queryKey: ["admin", "monetization"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="space-y-6">
      <section className="rounded-xl border bg-card p-4 flex flex-wrap items-end gap-3">
        <div>
          <Label htmlFor="slots">Sponsored slots per search page</Label>
          <Input id="slots" type="number" min={0} max={10} className="mt-1 w-24" value={slots} onChange={(e) => setSlots(Number(e.target.value))} />
        </div>
        <div>
          <Label htmlFor="homepage-slots">Homepage featured slots</Label>
          <Input id="homepage-slots" type="number" min={1} max={24} className="mt-1 w-24" value={homepage} onChange={(e) => setHomepage(Number(e.target.value))} />
        </div>
        <Button variant="outline" disabled={saveSlots.isPending} onClick={() => saveSlots.mutate()}>Save slots</Button>
      </section>

      <section className="rounded-xl border bg-card p-4 flex flex-wrap items-end gap-3">
        <div>
          <Label htmlFor="pl-school">School</Label>
          <select id="pl-school" className={`${SELECT} mt-1 block max-w-[220px]`} value={schoolId} onChange={(e) => setSchoolId(e.target.value)}>
            <option value="">Choose…</option>
            {schools?.schools.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
        </div>
        <div>
          <Label htmlFor="pl-placement">Placement</Label>
          <select id="pl-placement" className={`${SELECT} mt-1 block`} value={placement} onChange={(e) => setPlacement(e.target.value as PlacementRow["placement"])}>
            <option value="search_top">Top of search</option>
            <option value="homepage">Homepage</option>
            <option value="locality">Top of a locality</option>
          </select>
        </div>
        {placement === "locality" && (
          <div>
            <Label htmlFor="pl-locality">Locality</Label>
            <select id="pl-locality" className={`${SELECT} mt-1 block`} value={localityId} onChange={(e) => setLocalityId(e.target.value)}>
              <option value="">Choose…</option>
              {localities?.map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
            </select>
          </div>
        )}
        <div>
          <Label htmlFor="pl-days">Days</Label>
          <Input id="pl-days" type="number" min={1} max={365} className="mt-1 w-24" value={days} onChange={(e) => setDays(Number(e.target.value))} />
        </div>
        <Button disabled={!schoolId || (placement === "locality" && !localityId) || create.isPending} onClick={() => create.mutate()}>
          Start campaign
        </Button>
      </section>

      {isLoading ? (
        <Skeleton className="h-32 rounded-xl" />
      ) : !placements?.length ? (
        <div className="py-10 text-center text-sm text-muted-foreground rounded-xl border bg-card">No campaigns in the last 30 days.</div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {placements.map((p) => (
            <div key={p.id} className="px-4 py-3 flex flex-wrap items-center gap-3 text-sm">
              <div className="flex-1 min-w-[200px]">
                <div className="font-medium">{p.schoolName}</div>
                <div className="text-xs text-muted-foreground">
                  {p.placement === "locality" ? `Top of ${p.localityName}` : p.placement === "homepage" ? "Homepage" : "Top of search"} · {date(p.startsAt)} → {date(p.endsAt)}
                </div>
              </div>
              <span className={`text-xs ${p.live ? "text-green-700 dark:text-green-400" : "text-muted-foreground"}`}>{p.live ? "Live" : "Ended"}</span>
              {p.live && <Button size="sm" variant="ghost" onClick={() => end.mutate(p.id)}>End now</Button>}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/** DIQ-806: plans, invoices and sponsored placement for platform admins. */
export default function AdminBillingPage() {
  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Monetization</h1>
        <p className="text-sm text-muted-foreground mt-1">Revenue, invoices, school plans and sponsored placements</p>
      </div>
      <Tabs defaultValue="overview">
        <TabsList className="mb-4 flex-wrap h-auto">
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="invoices">Invoices</TabsTrigger>
          <TabsTrigger value="subscriptions">Subscriptions</TabsTrigger>
          <TabsTrigger value="placements">Placements</TabsTrigger>
        </TabsList>
        <TabsContent value="overview"><OverviewTab /></TabsContent>
        <TabsContent value="invoices"><InvoicesTab /></TabsContent>
        <TabsContent value="subscriptions"><SubscriptionsTab /></TabsContent>
        <TabsContent value="placements"><PlacementsTab /></TabsContent>
      </Tabs>
    </AdminLayout>
  );
}
