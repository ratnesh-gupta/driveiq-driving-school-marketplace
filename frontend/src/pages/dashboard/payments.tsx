import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useSchoolId } from "@/hooks/use-school-id";
import {
  listLearners,
  listPayments,
  markPaymentFailed,
  markPaymentPaid,
  purchasePackage,
  type PaymentRow,
} from "@/lib/ops-api";
import { useListPackages } from "@/api-client";
import { IndianRupee, Plus } from "lucide-react";
import { toast } from "sonner";

type LearnerRow = { id: number; name: string; status?: string };

export default function PaymentsPage() {
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [learnerId, setLearnerId] = useState("");
  const [packageId, setPackageId] = useState("");
  const [method, setMethod] = useState("cash");
  const [markPaid, setMarkPaid] = useState(true);

  const payments = useQuery({
    queryKey: ["payments", schoolId],
    queryFn: () => listPayments(schoolId!),
    enabled: !!schoolId,
  });

  const learners = useQuery({
    queryKey: ["learners", schoolId],
    queryFn: () => listLearners(schoolId!) as Promise<LearnerRow[]>,
    enabled: !!schoolId && open,
  });

  const packages = useListPackages({ schoolId: schoolId! }, { query: { enabled: !!schoolId && open } });

  const create = useMutation({
    mutationFn: () =>
      purchasePackage(schoolId!, {
        learnerId: Number(learnerId),
        packageId: Number(packageId),
        method,
        markPaid,
      }),
    onSuccess: () => {
      toast.success("Payment recorded");
      setOpen(false);
      setLearnerId("");
      setPackageId("");
      void qc.invalidateQueries({ queryKey: ["payments"] });
      void qc.invalidateQueries({ queryKey: ["learners"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const paid = useMutation({
    mutationFn: (id: number) => markPaymentPaid(id),
    onSuccess: () => {
      toast.success("Marked paid");
      void qc.invalidateQueries({ queryKey: ["payments"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const failed = useMutation({
    mutationFn: (id: number) => markPaymentFailed(id),
    onSuccess: () => {
      toast.success("Marked failed");
      void qc.invalidateQueries({ queryKey: ["payments"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <DashboardLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Payments</h1>
          <p className="text-sm text-muted-foreground mt-1">Package invoices and cash / online collection</p>
        </div>
        <Button onClick={() => setOpen((v) => !v)}>
          <Plus className="h-4 w-4 mr-1" /> Record purchase
        </Button>
      </div>

      {open && (
        <div className="rounded-xl border bg-card p-4 mb-6 grid gap-3 sm:grid-cols-2">
          <div>
            <Label>Learner</Label>
            <select className="w-full h-9 rounded-md border bg-background px-2 text-sm" value={learnerId} onChange={(e) => setLearnerId(e.target.value)}>
              <option value="">Select learner</option>
              {(learners.data ?? []).map((l) => (
                <option key={l.id} value={l.id}>{l.name}</option>
              ))}
            </select>
          </div>
          <div>
            <Label>Package</Label>
            <select className="w-full h-9 rounded-md border bg-background px-2 text-sm" value={packageId} onChange={(e) => setPackageId(e.target.value)}>
              <option value="">Select package</option>
              {(packages.data ?? []).map((p: { id: number; name: string; price?: number }) => (
                <option key={p.id} value={p.id}>{p.name} {p.price != null ? `· ₹${p.price}` : ""}</option>
              ))}
            </select>
          </div>
          <div>
            <Label>Method</Label>
            <select className="w-full h-9 rounded-md border bg-background px-2 text-sm" value={method} onChange={(e) => setMethod(e.target.value)}>
              <option value="cash">Cash</option>
              <option value="upi">UPI</option>
              <option value="manual">Manual</option>
              <option value="razorpay">Razorpay (pending order)</option>
            </select>
          </div>
          <div className="flex items-end gap-2">
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={markPaid} onChange={(e) => setMarkPaid(e.target.checked)} />
              Mark paid immediately
            </label>
          </div>
          <div className="sm:col-span-2">
            <Button disabled={!learnerId || !packageId || create.isPending} onClick={() => create.mutate()}>
              Save payment
            </Button>
          </div>
        </div>
      )}

      {payments.isLoading ? (
        <div className="space-y-2">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !payments.data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <IndianRupee className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No payments yet. Record a package purchase to start.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {payments.data.map((p: PaymentRow) => (
            <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
              <div>
                <div className="font-medium text-sm">
                  ₹{p.amount} · {p.packageName || p.purpose}
                </div>
                <div className="text-xs text-muted-foreground">
                  {[p.learnerName, p.method, p.receipt].filter(Boolean).join(" · ")}
                </div>
              </div>
              <div className="flex items-center gap-2">
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                  p.status === "paid" ? "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400" :
                  p.status === "failed" ? "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400" :
                  "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400"
                }`}>{p.status}</span>
                {p.status === "pending" && (
                  <>
                    <Button size="sm" variant="outline" onClick={() => paid.mutate(p.id)}>Mark paid</Button>
                    <Button size="sm" variant="ghost" onClick={() => failed.mutate(p.id)}>Fail</Button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}
