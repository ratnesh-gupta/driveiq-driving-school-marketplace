import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { InstructorLayout } from "@/components/layout/instructor-layout";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { SessionCalendar, initialCalendarRange, type CalendarRange, type CalendarSession } from "@/components/schedule/session-calendar";
import { listInstructorSessions, markAttendance } from "@/lib/ops-api";
import { toast } from "sonner";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { useState } from "react";

export default function InstructorSessionsPage() {
  const qc = useQueryClient();
  const [picked, setPicked] = useState<CalendarSession | null>(null);
  const [summary, setSummary] = useState("");

  const [range, setRange] = useState<CalendarRange>(initialCalendarRange);

  const { data, isLoading } = useQuery({
    queryKey: ["instructor", "sessions", range.from, range.to],
    queryFn: () => listInstructorSessions(range) as Promise<CalendarSession[]>,
    placeholderData: keepPreviousData,
  });

  const attend = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      markAttendance(id, { status, sessionSummary: summary.trim() || undefined }),
    onSuccess: () => {
      toast.success("Attendance saved");
      setPicked(null);
      setSummary("");
      void qc.invalidateQueries({ queryKey: ["instructor", "sessions"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <InstructorLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Sessions</h1>
        <p className="text-sm text-muted-foreground mt-1">Your week and month training calendar</p>
      </div>

      {isLoading ? (
        <Skeleton className="h-[420px] rounded-xl" />
      ) : (
        <SessionCalendar sessions={data ?? []} onSelectSession={(s) => { setPicked(s); setSummary(""); }} onRangeChange={setRange} />
      )}

      {picked && (picked.status === "scheduled" || picked.status === "rescheduled") && (
        <div className="mt-4 rounded-xl border bg-card p-4 space-y-3" data-testid="attendance-panel">
          <div className="text-sm">
            Mark attendance for <span className="font-medium">{picked.sessionDate} {picked.startTime}</span>
            {picked.learnerName ? ` · ${picked.learnerName}` : ""}
          </div>
          <div>
            <Label htmlFor="att-summary">What was covered (shown to the learner)</Label>
            <Textarea id="att-summary" rows={2} value={summary} onChange={(e) => setSummary(e.target.value)} placeholder="e.g. Clutch control on slopes, reverse parking" />
          </div>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" disabled={attend.isPending} onClick={() => attend.mutate({ id: picked.id, status: "present" })}>Present</Button>
            <Button size="sm" variant="outline" disabled={attend.isPending} onClick={() => attend.mutate({ id: picked.id, status: "absent" })}>Absent</Button>
            <Button size="sm" variant="outline" disabled={attend.isPending} onClick={() => attend.mutate({ id: picked.id, status: "rescheduled" })}>Rescheduled</Button>
            <Button size="sm" variant="outline" disabled={attend.isPending} onClick={() => attend.mutate({ id: picked.id, status: "cancelled" })}>Cancelled</Button>
            <Button size="sm" variant="ghost" onClick={() => setPicked(null)}>Close</Button>
          </div>
        </div>
      )}
    </InstructorLayout>
  );
}
