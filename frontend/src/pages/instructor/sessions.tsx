import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { InstructorLayout } from "@/components/layout/instructor-layout";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { listInstructorSessions, markAttendance } from "@/lib/ops-api";
import { CalendarDays } from "lucide-react";
import { toast } from "sonner";

type SessionRow = {
  id: number;
  sessionDate: string;
  startTime: string;
  endTime: string;
  learnerName?: string;
  pickupLocation?: string;
  status: string;
  attendance?: { status: string } | null;
};

export default function InstructorSessionsPage() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ["instructor", "sessions"],
    queryFn: listInstructorSessions,
  });

  const attend = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) => markAttendance(id, { status }),
    onSuccess: () => {
      toast.success("Attendance saved");
      void qc.invalidateQueries({ queryKey: ["instructor", "sessions"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <InstructorLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Sessions</h1>
        <p className="text-sm text-muted-foreground mt-1">Upcoming and past training sessions</p>
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground rounded-xl border bg-card">
          <CalendarDays className="h-10 w-10 mx-auto mb-2 opacity-30" />
          Sessions assigned to you will appear here once scheduled by the school.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((s: SessionRow) => (
            <div key={s.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
              <div>
                <div className="font-medium text-sm">{s.sessionDate} · {s.startTime}–{s.endTime}</div>
                <div className="text-xs text-muted-foreground">{[s.learnerName, s.pickupLocation].filter(Boolean).join(" · ") || "—"}</div>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs px-2 py-0.5 rounded-full bg-muted font-medium">{s.attendance?.status || s.status}</span>
                {s.status === "scheduled" && (
                  <>
                    <Button size="sm" variant="outline" onClick={() => attend.mutate({ id: s.id, status: "present" })}>Present</Button>
                    <Button size="sm" variant="ghost" onClick={() => attend.mutate({ id: s.id, status: "absent" })}>Absent</Button>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </InstructorLayout>
  );
}
