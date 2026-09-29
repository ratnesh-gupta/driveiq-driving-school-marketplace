import { useState } from "react";
import { Link } from "wouter";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Check, FileText, Receipt, Sparkles } from "lucide-react";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useSchoolId } from "@/hooks/use-school-id";
import { useEntitlements } from "@/hooks/use-entitlements";
import { useAuthStore } from "@/lib/store";
import {
  cancelPlanInvoice,
  listPlans,
  listSchoolInvoices,
  requestPlanInvoice,
  type PlatformInvoice,
} from "@/lib/ops-api";
import { PLAN_LABEL, PLAN_MATRIX, daysUntil, formatInr } from "@/lib/plan";

const TERMS = [1, 3, 6, 12];

const STATUS_STYLE: Record<PlatformInvoice["status"], string> = {
  issued: "bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300",
  paid: "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300",
  void: "bg-muted text-muted-foreground",
};

function PaymentInstructions({ invoice }: { invoice: PlatformInvoice }) {
  const p = invoice.paymentInstructions ?? {};
  return (
    <div className="rounded-xl border-2 border-primary/40 bg-primary/5 p-5" data-testid="payment-instructions">
      <h3 className="font-semibold">Pay {formatInr(invoice.total)} for {invoice.invoiceNumber}</h3>
      <p className="text-sm text-muted-foreground mt-1">
        Pay by UPI or bank transfer and put <strong>{invoice.invoiceNumber}</strong> in the payment note. We activate your plan
        once the payment is confirmed, usually within one working day.
      </p>
      <dl className="grid sm:grid-cols-2 gap-x-6 gap-y-1 text-sm mt-3">
        {p.upi_id && <><dt className="text-muted-foreground">UPI ID</dt><dd className="font-mono">{p.upi_id}</dd></>}
        {p.bank_name && <><dt className="text-muted-foreground">Bank</dt><dd>{p.bank_name}</dd></>}
        {p.account_name && <><dt className="text-muted-foreground">Account name</dt><dd>{p.account_name}</dd></>}
        {p.account_number && <><dt className="text-muted-foreground">Account number</dt><dd className="font-mono">{p.account_number}</dd></>}
        {p.ifsc && <><dt className="text-muted-foreground">IFSC</dt><dd className="font-mono">{p.ifsc}</dd></>}
      </dl>
      {!Object.keys(p).length && (
        <p className="text-sm mt-3">Payment details will be emailed to you by {invoice.seller?.email ?? "our billing team"}.</p>
      )}
    </div>
  );
}

/** DIQ-804: the school's plan, trial, plan comparison, upgrade and invoices. */
export default function BillingPage() {
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const isOwner = useAuthStore((s) => s.schoolRole) !== "manager";
  const { entitlements, isLoading: entLoading } = useEntitlements();
  const { data: plans } = useQuery({ queryKey: ["plans"], queryFn: listPlans });
  const { data: invoices, isLoading: invLoading } = useQuery({
    queryKey: ["platform-invoices", schoolId],
    queryFn: () => listSchoolInvoices(schoolId!),
    enabled: !!schoolId,
  });

  const [planCode, setPlanCode] = useState("premium");
  const [months, setMonths] = useState(1);
  const [gstin, setGstin] = useState("");

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: ["platform-invoices", schoolId] });
  };

  const request = useMutation({
    mutationFn: () => requestPlanInvoice(schoolId!, { planCode, months, ...(gstin.trim() ? { gstin: gstin.trim() } : {}) }),
    onSuccess: () => {
      toast.success("Invoice created — payment details below");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const cancel = useMutation({
    mutationFn: (id: number) => cancelPlanInvoice(schoolId!, id),
    onSuccess: () => {
      toast.success("Invoice cancelled");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const openInvoice = invoices?.find((i) => i.status === "issued");
  const price = (code: string) => plans?.find((p) => p.code === code)?.priceMonthly ?? 0;
  const selectedSubtotal = price(planCode) * months;

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Plan &amp; billing</h1>
        <p className="text-sm text-muted-foreground mt-1">Your plan decides which modules you can use and how you appear in search.</p>
      </div>

      {entLoading || !entitlements ? (
        <Skeleton className="h-24 rounded-xl mb-6" />
      ) : (
        <div className="rounded-xl border bg-card p-5 mb-6 flex flex-wrap gap-6 items-center" data-testid="current-plan">
          <div>
            <div className="text-xs text-muted-foreground">Current plan</div>
            <div className="text-xl font-bold">{PLAN_LABEL[entitlements.plan] ?? entitlements.plan}</div>
            {entitlements.planExpiresAt && (
              <div className="text-xs text-muted-foreground">Renews / ends {new Date(entitlements.planExpiresAt).toLocaleDateString()}</div>
            )}
          </div>
          {entitlements.trial.active && (
            <div className="flex items-center gap-2 text-sm">
              <Sparkles className="h-4 w-4 text-primary" />
              {PLAN_LABEL[entitlements.trial.plan]} features on trial for {daysUntil(entitlements.trial.endsAt)} more days
            </div>
          )}
        </div>
      )}

      {openInvoice && <div className="mb-8"><PaymentInstructions invoice={openInvoice} /></div>}

      <h2 className="font-semibold mb-3">Plans</h2>
      <div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        {PLAN_MATRIX.map((p) => {
          const current = entitlements?.plan === p.code;
          const selectable = p.code !== "basic";
          return (
            <button
              key={p.code}
              type="button"
              disabled={!selectable}
              onClick={() => setPlanCode(p.code)}
              className={`text-left rounded-xl border bg-card p-4 transition ${planCode === p.code && selectable ? "ring-2 ring-primary" : ""} ${selectable ? "hover:border-primary" : "opacity-80 cursor-default"}`}
              data-testid={`plan-${p.code}`}
            >
              <div className="flex items-center justify-between">
                <span className="font-semibold">{PLAN_LABEL[p.code]}</span>
                {current && <span className="text-xs rounded-full bg-primary/10 text-primary px-2 py-0.5">Current</span>}
              </div>
              <div className="text-lg font-bold mt-1">
                {price(p.code) ? `${formatInr(price(p.code))}` : "Free"}
                {price(p.code) ? <span className="text-xs font-normal text-muted-foreground"> / month + GST</span> : null}
              </div>
              <ul className="mt-3 space-y-1 text-sm">
                {p.highlights.map((h) => (
                  <li key={h} className="flex gap-2"><Check className="h-4 w-4 text-green-600 shrink-0 mt-0.5" />{h}</li>
                ))}
              </ul>
            </button>
          );
        })}
      </div>

      <section className="rounded-xl border bg-card p-5 mb-8">
        <h2 className="font-semibold">Upgrade to {PLAN_LABEL[planCode]}</h2>
        {!isOwner ? (
          <p className="text-sm text-muted-foreground mt-2">Only the school owner can change the plan.</p>
        ) : openInvoice ? (
          <p className="text-sm text-muted-foreground mt-2">
            Pay or cancel invoice {openInvoice.invoiceNumber} before requesting another.
          </p>
        ) : (
          <div className="grid sm:grid-cols-[auto_1fr_auto] gap-3 items-end mt-3">
            <div>
              <Label htmlFor="billing-term">Term</Label>
              <select
                id="billing-term"
                className="mt-1 h-9 rounded-md border bg-background px-3 text-sm"
                value={months}
                onChange={(e) => setMonths(Number(e.target.value))}
              >
                {TERMS.map((m) => <option key={m} value={m}>{m} month{m > 1 ? "s" : ""}</option>)}
              </select>
            </div>
            <div>
              <Label htmlFor="billing-gstin">GSTIN (optional, for input tax credit)</Label>
              <Input id="billing-gstin" className="mt-1" placeholder="27ABCDE1234F1Z5" value={gstin} onChange={(e) => setGstin(e.target.value)} />
            </div>
            <Button disabled={request.isPending} onClick={() => request.mutate()} data-testid="button-request-invoice">
              <Receipt className="h-4 w-4 mr-1" /> Get invoice · {formatInr(Math.round(selectedSubtotal * 1.18))}
            </Button>
          </div>
        )}
      </section>

      <h2 className="font-semibold mb-3">Invoices</h2>
      {invLoading ? (
        <Skeleton className="h-16 rounded-xl" />
      ) : !invoices?.length ? (
        <div className="py-8 text-center text-sm text-muted-foreground rounded-xl border bg-card">No invoices yet.</div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {invoices.map((inv) => (
            <div key={inv.id} className="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
              <FileText className="h-4 w-4 text-muted-foreground" />
              <div className="flex-1 min-w-[180px]">
                <div className="font-medium">{inv.invoiceNumber} · {PLAN_LABEL[inv.planCode]} × {inv.months} mo</div>
                <div className="text-xs text-muted-foreground">
                  {new Date(inv.issuedAt).toLocaleDateString()} · {formatInr(inv.total)} incl. GST
                  {inv.paymentReference ? ` · ref ${inv.paymentReference}` : ""}
                </div>
              </div>
              <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${STATUS_STYLE[inv.status]}`}>{inv.status}</span>
              <Button size="sm" variant="outline" asChild>
                <Link href={`/dashboard/billing/invoices/${inv.id}`}>View</Link>
              </Button>
              {inv.status === "issued" && isOwner && (
                <Button size="sm" variant="ghost" disabled={cancel.isPending} onClick={() => cancel.mutate(inv.id)}>Cancel</Button>
              )}
            </div>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}
