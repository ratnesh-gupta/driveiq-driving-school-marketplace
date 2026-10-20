import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { createDrivingTest, listDrivingTests, updateDrivingTest, type DrivingTestRow } from "@/lib/ops-api";

const STATUS_TONE: Record<DrivingTestRow["status"], string> = {
  scheduled: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
  completed: "bg-muted text-muted-foreground",
  passed: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  failed: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
};

/** RTO driving test attempts; staff can book and record results (DIQ-906 / DIQ-910). */
export function DrivingTestsPanel({ learnerId, canEdit }: { learnerId: number; canEdit: boolean }) {
  const qc = useQueryClient();
  const key = ["driving-tests", learnerId];
  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => listDrivingTests(learnerId) });
  const [testDate, setTestDate] = useState("");
  const [rtoName, setRtoName] = useState("");

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: key });
    void qc.invalidateQueries({ queryKey: ["learner", learnerId] });
  };
  const create = useMutation({
    mutationFn: () => createDrivingTest(learnerId, { testDate, rtoName: rtoName || undefined }),
    onSuccess: () => {
      toast.success("Test booked");
      setTestDate("");
      setRtoName("");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const result = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) => updateDrivingTest(id, { status }),
    onSuccess: () => {
      toast.success("Result saved");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div className="space-y-4">
      {canEdit && (
        <div className="grid gap-2 sm:grid-cols-[1fr_1fr_auto] items-end rounded-lg border p-3">
          <div><Label htmlFor="dt-date">Test date</Label><Input id="dt-date" type="date" value={testDate} onChange={(e) => setTestDate(e.target.value)} /></div>
          <div><Label htmlFor="dt-rto">RTO</Label><Input id="dt-rto" value={rtoName} onChange={(e) => setRtoName(e.target.value)} placeholder="e.g. RTO Pune (MH12)" /></div>
          <Button disabled={!testDate || create.isPending} onClick={() => create.mutate()}>Book test</Button>
        </div>
      )}
      {isLoading ? (
        <Skeleton className="h-20 rounded-lg" />
      ) : !data?.length ? (
        <p className="text-sm text-muted-foreground">No driving tests yet.</p>
      ) : (
        <ul className="divide-y rounded-lg border">
          {data.map((t) => (
            <li key={t.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 text-sm" data-testid={`driving-test-${t.id}`}>
              <div>
                <div className="font-medium">Attempt {t.attemptNumber} · {new Date(t.testDate).toLocaleDateString()}</div>
                <div className="text-xs text-muted-foreground">{[t.rtoName, t.rtoLocation].filter(Boolean).join(", ") || "RTO not set"}</div>
              </div>
              <div className="flex items-center gap-2">
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${STATUS_TONE[t.status]}`}>{t.status}</span>
                {canEdit && t.status === "scheduled" && (
                  <>
                    <Button size="sm" variant="outline" onClick={() => result.mutate({ id: t.id, status: "passed" })}>Passed</Button>
                    <Button size="sm" variant="ghost" onClick={() => result.mutate({ id: t.id, status: "failed" })}>Failed</Button>
                  </>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
