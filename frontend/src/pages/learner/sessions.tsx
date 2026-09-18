import { LearnerLayout } from "@/components/layout/learner-layout";
import { useQuery } from "@tanstack/react-query";
import { fetchLearnerMe } from "@/lib/ops-api";
import { SessionCalendar, type CalendarSession } from "@/components/schedule/session-calendar";
import { Skeleton } from "@/components/ui/skeleton";

export default function LearnerSessionsPage() {
  const { data, isLoading } = useQuery({ queryKey: ["learner", "me"], queryFn: fetchLearnerMe });
  const sessions = ((data?.upcomingSessions ?? []) as CalendarSession[]).map((s, i) => ({
    ...s,
    id: s.id ?? i,
  }));

  return (
    <LearnerLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Sessions</h1>
        <p className="text-sm text-muted-foreground mt-1">See your training week and month at a glance</p>
      </div>
      {isLoading ? (
        <Skeleton className="h-[420px] rounded-xl" />
      ) : (
        <SessionCalendar sessions={sessions} emptyLabel="No sessions on this day" />
      )}
    </LearnerLayout>
  );
}
