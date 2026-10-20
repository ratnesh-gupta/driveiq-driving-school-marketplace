import { useEffect, useState } from "react";
import { PlanGateBanner } from "@/components/plan/plan-gate-banner";
import { useEntitlements } from "@/hooks/use-entitlements";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { SessionCalendar, initialCalendarRange, type CalendarRange, type CalendarSession } from "@/components/schedule/session-calendar";
import { useSchoolId } from "@/hooks/use-school-id";
import {
  createSchedule,
  listInstructors,
  listLearners,
  listSchedules,
  listSchoolLeave,
  listVehicles,
  reviewLeave,
  updateSchedule,
  type LeaveRow,
  type ScheduleRow,
  type VehicleRow,
} from "@/lib/ops-api";
import { Plus } from "lucide-react";
import { toast } from "sonner";

type Named = { id: number; name: string; status?: string };

const SELECT = "w-full h-9 rounded-md border bg-background px-2 text-sm";

function SessionFields({
  value,
  onChange,
  instructors,
  vehicles,
}: {
  value: { instructorId: string; vehicleId: string; sessionDate: string; startTime: string; endTime: string; pickup: string };
  onChange: (patch: Partial<typeof value>) => void;
  instructors: Named[];
  vehicles: VehicleRow[];
}) {
  return (
    <>
      <div>
        <Label htmlFor="sf-instructor">Instructor</Label>
        <select id="sf-instructor" className={SELECT} value={value.instructorId} onChange={(e) => onChange({ instructorId: e.target.value })}>
          <option value="">Select</option>
          {instructors.map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
        </select>
      </div>
      <div>
        <Label htmlFor="sf-vehicle">Vehicle</Label>
        <select id="sf-vehicle" className={SELECT} value={value.vehicleId} onChange={(e) => onChange({ vehicleId: e.target.value })}>
          <option value="">No vehicle</option>
          {vehicles.map((v) => <option key={v.id} value={v.id}>{v.registrationNumber}{v.makeModel ? ` · ${v.makeModel}` : ""}</option>)}
        </select>
      </div>
      <div><Label htmlFor="sf-date">Date</Label><Input id="sf-date" type="date" value={value.sessionDate} onChange={(e) => onChange({ sessionDate: e.target.value })} /></div>
      <div><Label htmlFor="sf-start">Start</Label><Input id="sf-start" type="time" value={value.startTime} onChange={(e) => onChange({ startTime: e.target.value })} /></div>
      <div><Label htmlFor="sf-end">End</Label><Input id="sf-end" type="time" value={value.endTime} onChange={(e) => onChange({ endTime: e.target.value })} /></div>
      <div><Label htmlFor="sf-pickup">Pickup</Label><Input id="sf-pickup" value={value.pickup} onChange={(e) => onChange({ pickup: e.target.value })} placeholder="Optional" /></div>
    </>
  );
}

const emptySession = { instructorId: "", vehicleId: "", sessionDate: "", startTime: "07:00", endTime: "08:00", pickup: "" };

export default function SchedulesPage() {
  const canWrite = useEntitlements().has("schedules");
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [learnerId, setLearnerId] = useState("");
  const [draft, setDraft] = useState(emptySession);
  const [range, setRange] = useState<CalendarRange>(initialCalendarRange);
  const [instructorFilter, setInstructorFilter] = useState("");
  const [editing, setEditing] = useState<ScheduleRow | null>(null);
  const [edit, setEdit] = useState(emptySession);
  const [editNotes, setEditNotes] = useState("");
  const [clashes, setClashes] = useState<{ leave: LeaveRow; sessions: ScheduleRow[] } | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["schedules", schoolId, range.from, range.to, instructorFilter],
    queryFn: () => listSchedules(schoolId!, { ...range, instructorId: instructorFilter ? Number(instructorFilter) : undefined }) as Promise<ScheduleRow[]>,
    enabled: !!schoolId,
    placeholderData: keepPreviousData,
  });

  const learners = useQuery({
    queryKey: ["learners", schoolId],
    queryFn: () => listLearners(schoolId!) as Promise<Named[]>,
    enabled: !!schoolId && open,
  });
  const instructors = useQuery({
    queryKey: ["instructors", schoolId],
    queryFn: () => listInstructors(schoolId!) as Promise<Named[]>,
    enabled: !!schoolId,
  });
  const vehicles = useQuery({
    queryKey: ["vehicles", schoolId],
    queryFn: () => listVehicles(schoolId!) as Promise<VehicleRow[]>,
    enabled: !!schoolId,
  });
  const leave = useQuery({
    queryKey: ["school-leave", schoolId],
    queryFn: () => listSchoolLeave(schoolId!),
    enabled: !!schoolId,
  });

  // Only bookable trainers and vehicles are offered; the filter lists everyone.
  const activeInstructors = (instructors.data ?? []).filter((i) => i.status === "active");
  const activeVehicles = (vehicles.data ?? []).filter((v) => v.status === "active");
  const pendingLeave = (leave.data ?? []).filter((l) => l.status === "pending").length;

  const invalidate = () => void qc.invalidateQueries({ queryKey: ["schedules"] });

  const create = useMutation({
    mutationFn: () => {
      const learner = (learners.data ?? []).find((l) => String(l.id) === learnerId);
      return createSchedule(schoolId!, {
        instructorId: Number(draft.instructorId),
        vehicleId: draft.vehicleId ? Number(draft.vehicleId) : undefined,
        learnerId: learnerId ? Number(learnerId) : undefined,
        learnerName: learner?.name,
        sessionDate: draft.sessionDate,
        startTime: draft.startTime,
        endTime: draft.endTime,
        pickupLocation: draft.pickup || undefined,
      });
    },
    onSuccess: () => {
      toast.success("Session scheduled");
      setOpen(false);
      setDraft(emptySession);
      setLearnerId("");
      invalidate();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  useEffect(() => {
    if (!editing) return;
    setEdit({
      instructorId: String(editing.instructorId),
      vehicleId: editing.vehicleId ? String(editing.vehicleId) : "",
      sessionDate: editing.sessionDate,
      startTime: editing.startTime,
      endTime: editing.endTime,
      pickup: editing.pickupLocation ?? "",
    });
    setEditNotes(editing.notes ?? "");
  }, [editing]);

  const save = useMutation({
    mutationFn: (body: Record<string, unknown>) => updateSchedule(editing!.id, body),
    onSuccess: (_, body) => {
      toast.success(body.status === "cancelled" ? "Session cancelled" : "Session updated");
      setEditing(null);
      invalidate();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const review = useMutation({
    mutationFn: ({ id, status }: { id: number; status: "approved" | "rejected" }) => reviewLeave(id, status),
    onSuccess: (res) => {
      toast.success(`Leave ${res.status}`);
      void qc.invalidateQueries({ queryKey: ["school-leave"] });
      if (res.clashingSessions.length) setClashes({ leave: res, sessions: res.clashingSessions });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const editable = editing && (editing.status === "scheduled" || editing.status === "rescheduled") && canWrite;
  const editInstructors = (instructors.data ?? []).filter((i) => i.status === "active" || String(i.id) === edit.instructorId);
  const editVehicles = (vehicles.data ?? []).filter((v) => v.status === "active" || String(v.id) === edit.vehicleId);

  return (
    <DashboardLayout>
      <PlanGateBanner feature="schedules" />
      <div className="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
          <h1 className="text-2xl font-bold">Schedules</h1>
          <p className="text-sm text-muted-foreground mt-1">Training sessions, trainer leave and clashes</p>
        </div>
        <Button disabled={!canWrite} onClick={() => setOpen((v) => !v)} data-testid="button-new-session"><Plus className="h-4 w-4 mr-1" /> New session</Button>
      </div>

      {open && (
        <div className="rounded-xl border bg-card p-4 mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <Label htmlFor="ns-learner">Learner</Label>
            <select id="ns-learner" className={SELECT} value={learnerId} onChange={(e) => setLearnerId(e.target.value)}>
              <option value="">Select</option>
              {(learners.data ?? []).map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
            </select>
          </div>
          <SessionFields value={draft} onChange={(p) => setDraft((d) => ({ ...d, ...p }))} instructors={activeInstructors} vehicles={activeVehicles} />
          <div className="sm:col-span-2 lg:col-span-3">
            <Button disabled={!draft.instructorId || !draft.sessionDate || create.isPending} onClick={() => create.mutate()}>
              Save session
            </Button>
          </div>
        </div>
      )}

      <Tabs defaultValue="calendar">
        <TabsList>
          <TabsTrigger value="calendar">Calendar</TabsTrigger>
          <TabsTrigger value="leave" data-testid="tab-leave">Leave requests{pendingLeave ? ` (${pendingLeave})` : ""}</TabsTrigger>
        </TabsList>

        <TabsContent value="calendar" className="pt-3 space-y-3">
          <div className="flex items-center gap-2 max-w-xs">
            <Label htmlFor="filter-instructor" className="shrink-0">Trainer</Label>
            <select id="filter-instructor" className={SELECT} value={instructorFilter} onChange={(e) => setInstructorFilter(e.target.value)}>
              <option value="">All trainers</option>
              {(instructors.data ?? []).map((i) => <option key={i.id} value={i.id}>{i.name}{i.status !== "active" ? ` (${i.status})` : ""}</option>)}
            </select>
          </div>
          {isLoading ? (
            <Skeleton className="h-[420px] rounded-xl" />
          ) : (
            <SessionCalendar
              sessions={(data ?? []) as CalendarSession[]}
              onRangeChange={setRange}
              onSelectSession={(s) => setEditing((data ?? []).find((r) => r.id === s.id) ?? null)}
            />
          )}
        </TabsContent>

        <TabsContent value="leave" className="pt-3">
          {!leave.data?.length ? (
            <p className="text-sm text-muted-foreground">No leave requests yet. Trainers request leave from their portal.</p>
          ) : (
            <div className="rounded-xl border bg-card divide-y">
              {leave.data.map((l) => (
                <div key={l.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm" data-testid={`leave-${l.id}`}>
                  <div>
                    <div className="font-medium">{l.instructorName}</div>
                    <div className="text-xs text-muted-foreground">
                      {l.startDate}{l.endDate !== l.startDate ? ` → ${l.endDate}` : ""}{l.reason ? ` · ${l.reason}` : ""}
                    </div>
                  </div>
                  {l.status === "pending" && canWrite ? (
                    <div className="flex gap-2">
                      <Button size="sm" disabled={review.isPending} onClick={() => review.mutate({ id: l.id, status: "approved" })}>Approve</Button>
                      <Button size="sm" variant="outline" disabled={review.isPending} onClick={() => review.mutate({ id: l.id, status: "rejected" })}>Reject</Button>
                    </div>
                  ) : (
                    <span className="text-xs px-2 py-0.5 rounded-full bg-muted font-medium capitalize">{l.status}</span>
                  )}
                </div>
              ))}
            </div>
          )}
        </TabsContent>
      </Tabs>

      <Dialog open={!!editing} onOpenChange={(o) => { if (!o) setEditing(null); }}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{editing?.learnerName ?? "Session"} · {editing?.sessionDate} {editing?.startTime}</DialogTitle>
            <DialogDescription>
              {[editing?.status, editing?.attendance?.status && `attendance: ${editing.attendance.status}`].filter(Boolean).join(" · ")}
            </DialogDescription>
          </DialogHeader>
          {editable ? (
            <div className="space-y-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <SessionFields value={edit} onChange={(p) => setEdit((d) => ({ ...d, ...p }))} instructors={editInstructors} vehicles={editVehicles} />
                <div className="sm:col-span-2"><Label htmlFor="se-notes">Notes</Label><Textarea id="se-notes" rows={2} value={editNotes} onChange={(e) => setEditNotes(e.target.value)} /></div>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button
                  disabled={save.isPending || !edit.instructorId}
                  onClick={() => save.mutate({
                    instructorId: Number(edit.instructorId),
                    vehicleId: edit.vehicleId ? Number(edit.vehicleId) : null,
                    sessionDate: edit.sessionDate,
                    startTime: edit.startTime,
                    endTime: edit.endTime,
                    pickupLocation: edit.pickup || null,
                    notes: editNotes || null,
                  })}
                  data-testid="button-save-session"
                >
                  Save changes
                </Button>
                <Button variant="outline" className="text-destructive" disabled={save.isPending}
                  onClick={() => window.confirm("Cancel this session?") && save.mutate({ status: "cancelled" })}
                  data-testid="button-cancel-session">
                  Cancel session
                </Button>
              </div>
            </div>
          ) : (
            <div className="text-sm space-y-1">
              <div>Trainer: {editing?.instructorName ?? "—"}</div>
              <div>Vehicle: {editing?.vehicleRegistration ?? "—"}</div>
              {editing?.sessionSummary && <p className="pt-2 whitespace-pre-wrap">{editing.sessionSummary}</p>}
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={!!clashes} onOpenChange={(o) => { if (!o) setClashes(null); }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Sessions during this leave</DialogTitle>
            <DialogDescription>
              {clashes?.leave.instructorName} is now on leave, but these sessions are still booked. Open each one to move it to another trainer or cancel it.
            </DialogDescription>
          </DialogHeader>
          <ul className="divide-y rounded-lg border" data-testid="leave-clashes">
            {clashes?.sessions.map((s) => (
              <li key={s.id} className="flex items-center justify-between px-3 py-2 text-sm">
                <span>{s.sessionDate} {s.startTime}–{s.endTime} · {s.learnerName ?? "—"}</span>
                <Button size="sm" variant="outline" onClick={() => { setClashes(null); setEditing(s); }}>Open</Button>
              </li>
            ))}
          </ul>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
