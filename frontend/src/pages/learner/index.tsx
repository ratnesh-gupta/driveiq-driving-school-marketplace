import { useQuery } from "@tanstack/react-query";
import { LearnerLayout } from "@/components/layout/learner-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchLearnerMe } from "@/lib/ops-api";
import { GraduationCap } from "lucide-react";

export default function LearnerHomePage() {
  const { data, isLoading } = useQuery({
    queryKey: ["learner", "me"],
    queryFn: fetchLearnerMe,
  });

  const learner = data?.learner as Record<string, unknown> | undefined;
  const sessions = (data?.upcomingSessions as { sessionDate?: string; startTime?: string; endTime?: string; status?: string }[]) ?? [];

  return (
    <LearnerLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">My training</h1>
        <p className="text-sm text-muted-foreground mt-1">Profile, trainer, and upcoming sessions</p>
      </div>

      {isLoading ? (
        <Skeleton className="h-40 rounded-xl" />
      ) : !learner ? (
        <div className="py-16 text-center text-muted-foreground">
          <GraduationCap className="h-10 w-10 mx-auto mb-2 opacity-30" />
          Learner profile not found.
        </div>
      ) : (
        <div className="space-y-4">
          <div className="rounded-xl border bg-card p-6 grid sm:grid-cols-2 gap-3 text-sm">
            <div><span className="text-muted-foreground">Name:</span> {String(learner.name)}</div>
            <div><span className="text-muted-foreground">Status:</span> {String(learner.status)}</div>
            <div><span className="text-muted-foreground">Trainer:</span> {String(learner.instructorName ?? "Not assigned")}</div>
            <div><span className="text-muted-foreground">Vehicle:</span> {String(learner.vehicleRegistration ?? "—")}</div>
            <div><span className="text-muted-foreground">Package:</span> {String(learner.packageName ?? "—")}</div>
            <div><span className="text-muted-foreground">Start:</span> {String(learner.startDate ?? "—")}</div>
          </div>

          <div className="rounded-xl border bg-card">
            <div className="px-5 py-3 border-b font-semibold text-sm">Upcoming sessions</div>
            {!sessions.length ? (
              <div className="py-8 text-center text-sm text-muted-foreground">No upcoming sessions</div>
            ) : (
              <div className="divide-y">
                {sessions.map((s, idx) => (
                  <div key={idx} className="px-5 py-3 text-sm flex justify-between">
                    <span>{s.sessionDate} · {s.startTime}–{s.endTime}</span>
                    <span className="text-xs text-muted-foreground">{s.status}</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      )}
    </LearnerLayout>
  );
}
