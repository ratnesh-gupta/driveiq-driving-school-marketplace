import { LearnerLayout } from "@/components/layout/learner-layout";
import { useQuery } from "@tanstack/react-query";
import { fetchLearnerMe } from "@/lib/ops-api";
import { CalendarDays } from "lucide-react";

export default function LearnerSessionsPage() {
  const { data, isLoading } = useQuery({ queryKey: ["learner", "me"], queryFn: fetchLearnerMe });
  const sessions = (data?.upcomingSessions as { id?: number; sessionDate?: string; startTime?: string; endTime?: string; status?: string; pickupLocation?: string }[]) ?? [];

  return (
    <LearnerLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Sessions</h1>
        <p className="text-sm text-muted-foreground mt-1">Your scheduled training</p>
      </div>
      {isLoading ? null : !sessions.length ? (
        <div className="py-16 text-center text-muted-foreground rounded-xl border bg-card">
          <CalendarDays className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No sessions yet.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {sessions.map((s, i) => (
            <div key={s.id ?? i} className="px-5 py-3.5 flex justify-between text-sm">
              <div>
                <div className="font-medium">{s.sessionDate} · {s.startTime}–{s.endTime}</div>
                <div className="text-xs text-muted-foreground">{s.pickupLocation || "—"}</div>
              </div>
              <span className="text-xs text-muted-foreground">{s.status}</span>
            </div>
          ))}
        </div>
      )}
    </LearnerLayout>
  );
}
