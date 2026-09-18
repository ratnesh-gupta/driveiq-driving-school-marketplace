import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useSchoolId } from "@/hooks/use-school-id";
import { createSchedule, listInstructors, listLearners, listSchedules } from "@/lib/ops-api";
import { CalendarDays, Plus } from "lucide-react";
import { toast } from "sonner";

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

type Named = { id: number; name: string };

export default function SchedulesPage() {
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [learnerId, setLearnerId] = useState("");
  const [instructorId, setInstructorId] = useState("");
  const [sessionDate, setSessionDate] = useState("");
  const [startTime, setStartTime] = useState("07:00");
  const [endTime, setEndTime] = useState("08:00");
  const [pickup, setPickup] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["schedules", schoolId],
    queryFn: () => listSchedules(schoolId!) as Promise<ScheduleRow[]>,
    enabled: !!schoolId,
  });

  const learners = useQuery({
    queryKey: ["learners", schoolId],
    queryFn: () => listLearners(schoolId!) as Promise<Named[]>,
    enabled: !!schoolId && open,
  });

  const instructors = useQuery({
    queryKey: ["instructors", schoolId],
    queryFn: () => listInstructors(schoolId!) as Promise<Named[]>,
    enabled: !!schoolId && open,
  });

  const create = useMutation({
    mutationFn: () => {
      const learner = (learners.data ?? []).find((l) => String(l.id) === learnerId);
      return createSchedule(schoolId!, {
        instructorId: Number(instructorId),
        learnerId: learnerId ? Number(learnerId) : undefined,
        learnerName: learner?.name,
        sessionDate,
        startTime,
        endTime,
        pickupLocation: pickup || undefined,
      });
    },
    onSuccess: () => {
      toast.success("Session scheduled");
      setOpen(false);
      void qc.invalidateQueries({ queryKey: ["schedules"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <DashboardLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Schedules</h1>
          <p className="text-sm text-muted-foreground mt-1">Training sessions across your fleet</p>
        </div>
        <Button onClick={() => setOpen((v) => !v)}><Plus className="h-4 w-4 mr-1" /> New session</Button>
      </div>

      {open && (
        <div className="rounded-xl border bg-card p-4 mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <Label>Learner</Label>
            <select className="w-full h-9 rounded-md border bg-background px-2 text-sm" value={learnerId} onChange={(e) => setLearnerId(e.target.value)}>
              <option value="">Select</option>
              {(learners.data ?? []).map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
            </select>
          </div>
          <div>
            <Label>Instructor</Label>
            <select className="w-full h-9 rounded-md border bg-background px-2 text-sm" value={instructorId} onChange={(e) => setInstructorId(e.target.value)}>
              <option value="">Select</option>
              {(instructors.data ?? []).map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
            </select>
          </div>
          <div>
            <Label>Date</Label>
            <Input type="date" value={sessionDate} onChange={(e) => setSessionDate(e.target.value)} />
          </div>
          <div>
            <Label>Start</Label>
            <Input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} />
          </div>
          <div>
            <Label>End</Label>
            <Input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} />
          </div>
          <div>
            <Label>Pickup</Label>
            <Input value={pickup} onChange={(e) => setPickup(e.target.value)} placeholder="Optional" />
          </div>
          <div className="sm:col-span-2 lg:col-span-3">
            <Button disabled={!instructorId || !sessionDate || create.isPending} onClick={() => create.mutate()}>
              Save session
            </Button>
          </div>
        </div>
      )}

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
