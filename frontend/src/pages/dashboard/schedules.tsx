import { useQuery } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { useSchoolId } from "@/hooks/use-school-id";
import { listSchedules } from "@/lib/ops-api";
import { CalendarDays } from "lucide-react";

type ScheduleRow = {
  id: number;
  sessionDate: string;
  startTime: string;
  endTime: string;
  learnerName?: string;
  instructorName?: string;
  status: string;
  pickupLocation?: string;
};

export default function SchedulesPage() {
  const schoolId = useSchoolId();
  const { data, isLoading } = useQuery({
    queryKey: ["schedules", schoolId],
    queryFn: () => listSchedules(schoolId!) as Promise<ScheduleRow[]>,
    enabled: !!schoolId,
  });

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Schedules</h1>
        <p className="text-sm text-muted-foreground mt-1">Training sessions across your fleet</p>
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <CalendarDays className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No sessions scheduled.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((s) => (
            <div key={s.id} className="flex items-center justify-between px-5 py-3.5">
              <div>
                <div className="font-medium text-sm">
                  {s.sessionDate} · {s.startTime}–{s.endTime}
                </div>
                <div className="text-xs text-muted-foreground">
                  {[s.learnerName, s.instructorName, s.pickupLocation].filter(Boolean).join(" · ") || "—"}
                </div>
              </div>
              <span className="text-xs px-2 py-0.5 rounded-full bg-muted font-medium">{s.status}</span>
            </div>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}
