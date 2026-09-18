import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { InstructorLayout } from "@/components/layout/instructor-layout";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { SessionCalendar, type CalendarSession } from "@/components/schedule/session-calendar";
import { listInstructorSessions, markAttendance } from "@/lib/ops-api";
import { toast } from "sonner";
import { useState } from "react";

export default function InstructorSessionsPage() {
  const qc = useQueryClient();
  const [picked, setPicked] = useState<CalendarSession | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["instructor", "sessions"],
    queryFn: () => listInstructorSessions() as Promise<CalendarSession[]>,
  });

  const attend = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) => markAttendance(id, { status }),
    onSuccess: () => {
      toast.success("Attendance saved");
      setPicked(null);
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
        <SessionCalendar sessions={data ?? []} onSelectSession={setPicked} />
      )}

      {picked && picked.status === "scheduled" && (
        <div className="mt-4 rounded-xl border bg-card p-4 flex flex-wrap items-center justify-between gap-3">
          <div className="text-sm">
            Mark attendance for <span className="font-medium">{picked.sessionDate} {picked.startTime}</span>
            {picked.learnerName ? ` · ${picked.learnerName}` : ""}
          </div>
          <div className="flex gap-2">
            <Button size="sm" onClick={() => attend.mutate({ id: picked.id, status: "present" })}>Present</Button>
            <Button size="sm" variant="outline" onClick={() => attend.mutate({ id: picked.id, status: "absent" })}>Absent</Button>
            <Button size="sm" variant="ghost" onClick={() => setPicked(null)}>Cancel</Button>
          </div>
        </div>
      )}
    </InstructorLayout>
  );
}
