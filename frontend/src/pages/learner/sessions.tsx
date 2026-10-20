import { LearnerLayout } from "@/components/layout/learner-layout";
import { useQuery } from "@tanstack/react-query";
import { fetchLearnerMe } from "@/lib/ops-api";
import { SessionCalendar, type CalendarSession } from "@/components/schedule/session-calendar";
import { SessionHistory } from "@/components/learners/session-history";
import { Skeleton } from "@/components/ui/skeleton";

export default function LearnerSessionsPage() {
  const { data, isLoading } = useQuery({ queryKey: ["learner", "me"], queryFn: fetchLearnerMe });
  const learnerId = (data?.learner as { id?: number } | undefined)?.id;
  const sessions = ((data?.upcomingSessions ?? []) as CalendarSession[]).map((s, i) => ({
    ...s,
    id: s.id ?? i,
  }));

  return (
    <LearnerLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Sessions</h1>
        <p className="text-sm text-muted-foreground mt-1">Upcoming sessions, and what your trainer covered in past ones</p>
      </div>
      {isLoading ? (
        <Skeleton className="h-[420px] rounded-xl" />
      ) : (
        <div className="space-y-8">
          <SessionCalendar sessions={sessions} emptyLabel="No sessions on this day" />
          {learnerId && (
            <div>
              <h2 className="text-lg font-semibold mb-3">History and trainer feedback</h2>
              <SessionHistory learnerId={learnerId} />
            </div>
          )}
        </div>
      )}
    </LearnerLayout>
  );
}
