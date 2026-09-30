import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { InstructorLayout } from "@/components/layout/instructor-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { listMyLeave, requestMyLeave } from "@/lib/ops-api";

const TONE: Record<string, string> = {
  pending: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400",
  approved: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  rejected: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
};

/** Trainer asks for leave; the school approves or rejects it (DIQ-908). */
export default function InstructorLeavePage() {
  const qc = useQueryClient();
  const today = new Date().toISOString().slice(0, 10);
  const [startDate, setStartDate] = useState("");
  const [endDate, setEndDate] = useState("");
  const [reason, setReason] = useState("");
  const { data, isLoading } = useQuery({ queryKey: ["instructor", "leave"], queryFn: listMyLeave });

  const submit = useMutation({
    mutationFn: () => requestMyLeave({ startDate, endDate: endDate || startDate, reason: reason.trim() || undefined }),
    onSuccess: () => {
      toast.success("Leave requested. Your school will review it.");
      setStartDate("");
      setEndDate("");
      setReason("");
      void qc.invalidateQueries({ queryKey: ["instructor", "leave"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <InstructorLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Leave</h1>
        <p className="text-sm text-muted-foreground mt-1">Once approved, no new sessions can be booked with you on those days</p>
      </div>

      <div className="rounded-xl border bg-card p-4 mb-6 grid gap-3 sm:grid-cols-[1fr_1fr_2fr_auto] items-end">
        <div><Label htmlFor="lv-from">From</Label><Input id="lv-from" type="date" min={today} value={startDate} onChange={(e) => setStartDate(e.target.value)} /></div>
        <div><Label htmlFor="lv-to">To</Label><Input id="lv-to" type="date" min={startDate || today} value={endDate} onChange={(e) => setEndDate(e.target.value)} /></div>
        <div><Label htmlFor="lv-reason">Reason (optional)</Label><Input id="lv-reason" value={reason} onChange={(e) => setReason(e.target.value)} /></div>
        <Button disabled={!startDate || submit.isPending} onClick={() => submit.mutate()} data-testid="button-request-leave">Request</Button>
      </div>

      {isLoading ? (
        <Skeleton className="h-24 rounded-xl" />
      ) : !data?.length ? (
        <p className="text-sm text-muted-foreground">No leave requests yet.</p>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((l) => (
            <div key={l.id} className="flex items-center justify-between px-5 py-3 text-sm" data-testid={`my-leave-${l.id}`}>
              <div>
                <div className="font-medium">
                  {new Date(l.startDate).toLocaleDateString()}
                  {l.endDate !== l.startDate && ` – ${new Date(l.endDate).toLocaleDateString()}`}
                </div>
                {l.reason && <div className="text-xs text-muted-foreground">{l.reason}</div>}
              </div>
              <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${TONE[l.status]}`}>{l.status}</span>
            </div>
          ))}
        </div>
      )}
    </InstructorLayout>
  );
}
