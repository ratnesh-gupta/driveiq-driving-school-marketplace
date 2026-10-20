import { useQuery } from "@tanstack/react-query";
import { Skeleton } from "@/components/ui/skeleton";
import { listLearnerSessions } from "@/lib/ops-api";

const ATTENDANCE_TONE: Record<string, string> = {
  present: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  absent: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
  rescheduled: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400",
  cancelled: "bg-muted text-muted-foreground",
};

/** Past and upcoming sessions with the trainer's summary (DIQ-908 / DIQ-910). */
export function SessionHistory({ learnerId }: { learnerId: number }) {
  const { data, isLoading } = useQuery({ queryKey: ["learner-sessions", learnerId], queryFn: () => listLearnerSessions(learnerId) });

  if (isLoading) return <Skeleton className="h-24 rounded-lg" />;
  if (!data?.length) return <p className="text-sm text-muted-foreground">No sessions yet.</p>;

  return (
    <ul className="divide-y rounded-lg border" data-testid="session-history">
      {data.map((s) => (
        <li key={s.id} className="px-3 py-2.5 text-sm space-y-1">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span className="font-medium">
              {new Date(s.sessionDate).toLocaleDateString(undefined, { weekday: "short", day: "numeric", month: "short" })} · {s.startTime}–{s.endTime}
            </span>
            <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${ATTENDANCE_TONE[s.attendance ?? ""] ?? "bg-primary/10 text-primary"}`}>
              {s.attendance ?? s.status}
            </span>
          </div>
          {s.instructorName && <div className="text-xs text-muted-foreground">Trainer: {s.instructorName}</div>}
          {s.sessionSummary && <p className="text-sm whitespace-pre-wrap">{s.sessionSummary}</p>}
        </li>
      ))}
    </ul>
  );
}
