import { useQuery } from "@tanstack/react-query";
import { InstructorLayout } from "@/components/layout/instructor-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { DocumentsPanel } from "@/components/documents-panel";
import { WhatsAppOptInCard } from "@/components/whatsapp-opt-in-card";
import { fetchInstructorMe } from "@/lib/ops-api";
import { CalendarClock, CalendarDays, CheckCircle2, User, Users } from "lucide-react";

export default function InstructorHomePage() {
  const { data, isLoading } = useQuery({
    queryKey: ["instructor", "me"],
    queryFn: fetchInstructorMe,
  });

  const instructor = (data?.instructor ?? data) as Record<string, unknown> | undefined;
  const dash = data?.dashboard as
    | { todaySessions?: number; upcomingSessions?: number; assignedLearners?: number; attendanceRate?: number | null }
    | undefined;
  const cards = dash
    ? [
        { icon: CalendarDays, label: "Sessions today", value: dash.todaySessions ?? 0 },
        { icon: CalendarClock, label: "Next 7 days", value: dash.upcomingSessions ?? 0 },
        { icon: Users, label: "Active learners", value: dash.assignedLearners ?? 0 },
        {
          icon: CheckCircle2,
          label: "Attendance",
          value: dash.attendanceRate == null ? "—" : `${Math.round(dash.attendanceRate * 100)}%`,
        },
      ]
    : [];

  return (
    <InstructorLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Trainer overview</h1>
        <p className="text-sm text-muted-foreground mt-1">Your profile and assignment summary</p>
      </div>

      {isLoading ? (
        <Skeleton className="h-40 rounded-xl" />
      ) : !instructor ? (
        <div className="py-16 text-center text-muted-foreground">
          <User className="h-10 w-10 mx-auto mb-2 opacity-30" />
          Instructor profile not linked yet. Ask your school admin to link your account.
        </div>
      ) : (
        <>
        {cards.length > 0 && (
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6" data-testid="instructor-dashboard-cards">
            {cards.map((c) => (
              <div key={c.label} className="rounded-xl border bg-card p-4 flex items-center gap-3">
                <c.icon className="h-7 w-7 text-primary flex-shrink-0" />
                <div>
                  <div className="text-xl font-bold">{c.value}</div>
                  <div className="text-xs text-muted-foreground">{c.label}</div>
                </div>
              </div>
            ))}
          </div>
        )}
        <div className="rounded-xl border bg-card p-6 space-y-3">
          <div className="text-lg font-semibold">{String(instructor.name ?? "Trainer")}</div>
          <div className="grid sm:grid-cols-2 gap-3 text-sm">
            <div><span className="text-muted-foreground">Status:</span> {String(instructor.status ?? "—")}</div>
            <div><span className="text-muted-foreground">Mobile:</span> {String(instructor.mobile ?? "—")}</div>
            <div><span className="text-muted-foreground">Experience:</span> {String(instructor.yearsExperience ?? instructor.experienceYears ?? "—")} yrs</div>
            <div><span className="text-muted-foreground">Learners trained:</span> {String(instructor.totalLearnersTrained ?? 0)}</div>
          </div>
        </div>
        </>
      )}

      {typeof instructor?.id === "number" && (
        <div className="mt-6"><WhatsAppOptInCard audience="trainer" /></div>
      )}

      {typeof instructor?.id === "number" && (
        <div className="mt-8">
          <h2 className="text-lg font-semibold mb-1">My documents</h2>
          <p className="text-sm text-muted-foreground mb-4">Licence, ID and certificates for your school to verify.</p>
          <DocumentsPanel kind="instructor" ownerId={instructor.id} />
        </div>
      )}
    </InstructorLayout>
  );
}
