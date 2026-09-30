import { useState } from "react";
import { PlanGateBanner } from "@/components/plan/plan-gate-banner";
import { useEntitlements } from "@/hooks/use-entitlements";
import { useQuery } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { InstructorSheet } from "@/components/instructors/instructor-sheet";
import { useSchoolId } from "@/hooks/use-school-id";
import { useAuthStore } from "@/lib/store";
import {
  fetchSchoolInstructorAnalytics,
  listInstructors,
  type InstructorDetail,
  type InstructorPerformanceRow,
} from "@/lib/ops-api";
import { ChevronRight, KeyRound, UserCog, Plus } from "lucide-react";

const STATUS_TONE: Record<string, string> = {
  active: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  inactive: "bg-muted text-muted-foreground",
  terminated: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
};

const pct = (v: number) => `${Math.round(v * 100)}%`;

export default function InstructorsPage() {
  const entitlements = useEntitlements();
  const canWrite = entitlements.has("instructors");
  const hasAnalytics = entitlements.has("analytics_advanced");
  const isOwner = useAuthStore((s) => s.schoolRole) !== "manager";
  const schoolId = useSchoolId();
  const [sheet, setSheet] = useState<{ open: boolean; id: number | null }>({ open: false, id: null });

  const { data, isLoading } = useQuery({
    queryKey: ["instructors", schoolId],
    queryFn: () => listInstructors(schoolId!) as Promise<InstructorDetail[]>,
    enabled: !!schoolId,
  });

  const performance = useQuery({
    queryKey: ["instructor-performance", schoolId],
    queryFn: () => fetchSchoolInstructorAnalytics(schoolId!),
    enabled: !!schoolId && hasAnalytics,
  });
  const perf = (performance.data?.instructors ?? []) as InstructorPerformanceRow[];

  return (
    <DashboardLayout>
      <PlanGateBanner feature="instructors" />
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Instructors</h1>
          <p className="text-sm text-muted-foreground mt-1">Trainer profiles, logins, documents and workload</p>
        </div>
        <Button disabled={!canWrite} onClick={() => setSheet({ open: true, id: null })} data-testid="button-add-instructor">
          <Plus className="h-4 w-4 mr-1" /> Add instructor
        </Button>
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <UserCog className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No instructors yet.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((i) => (
            <button
              key={i.id}
              type="button"
              onClick={() => setSheet({ open: true, id: i.id })}
              className="w-full flex items-center justify-between px-5 py-3.5 text-left hover:bg-muted/40 transition-colors"
              data-testid={`row-instructor-${i.id}`}
            >
              <div>
                <div className="font-medium text-sm flex items-center gap-1.5">
                  {i.name}
                  {i.hasLogin && <KeyRound className="h-3.5 w-3.5 text-muted-foreground" aria-label="Has portal login" />}
                </div>
                <div className="text-xs text-muted-foreground">
                  {[i.mobile, i.yearsExperience ? `${i.yearsExperience} yrs` : null, (i.languages ?? []).join(", ") || null, i.publicVisible ? "Public" : null]
                    .filter(Boolean).join(" · ") || "—"}
                </div>
              </div>
              <div className="flex items-center gap-2">
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${STATUS_TONE[i.status] ?? "bg-muted"}`}>{i.status}</span>
                <ChevronRight className="h-4 w-4 text-muted-foreground" />
              </div>
            </button>
          ))}
        </div>
      )}

      {hasAnalytics && perf.length > 0 && (
        <div className="mt-8">
          <h2 className="text-lg font-semibold mb-3">Performance & workload</h2>
          <div className="rounded-xl border bg-card overflow-x-auto">
            <table className="w-full text-sm" data-testid="instructor-performance">
              <thead className="bg-muted/40">
                <tr>
                  {["Trainer", "Active learners", "Learners trained", "Sessions done", "Attendance", "Completion", "Hours this week"].map((h) => (
                    <th key={h} className="text-left px-4 py-2.5 text-xs font-semibold text-muted-foreground whitespace-nowrap">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y">
                {perf.map((r) => (
                  <tr key={r.instructorId}>
                    <td className="px-4 py-2.5 font-medium">{r.name}</td>
                    <td className="px-4 py-2.5 tabular-nums">{r.learnersAssigned}</td>
                    <td className="px-4 py-2.5 tabular-nums">{r.totalLearnersTrained}</td>
                    <td className="px-4 py-2.5 tabular-nums">{r.sessionsCompleted} / {r.sessionsTotal}</td>
                    <td className="px-4 py-2.5 tabular-nums">{pct(r.attendanceRate)}</td>
                    <td className="px-4 py-2.5 tabular-nums">{pct(r.completionRate)}</td>
                    <td className="px-4 py-2.5 tabular-nums">{r.hoursThisWeek}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="text-xs text-muted-foreground mt-2">Active trainers only. Attendance counts sessions marked present out of those marked.</p>
        </div>
      )}

      {schoolId && (
        <InstructorSheet
          open={sheet.open}
          instructor={(sheet.id !== null && data?.find((i) => i.id === sheet.id)) || null}
          schoolId={schoolId}
          canWrite={canWrite}
          isOwner={isOwner}
          onOpenChange={(o) => { if (!o) setSheet({ open: false, id: null }); }}
        />
      )}
    </DashboardLayout>
  );
}
