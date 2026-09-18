import { useQuery } from "@tanstack/react-query";
import { LearnerLayout } from "@/components/layout/learner-layout";
import { Progress } from "@/components/ui/progress";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchLearnerMe, fetchLearnerProgress } from "@/lib/ops-api";

export default function LearnerProgressPage() {
  const me = useQuery({ queryKey: ["learner", "me"], queryFn: fetchLearnerMe });
  const learnerId = (me.data?.learner as { id?: number } | undefined)?.id;

  const progress = useQuery({
    queryKey: ["learner", "progress", learnerId],
    queryFn: () => fetchLearnerProgress(learnerId!),
    enabled: !!learnerId,
  });

  return (
    <LearnerLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Training progress</h1>
        <p className="text-sm text-muted-foreground mt-1">Skill breakdown and overall completion</p>
      </div>

      {progress.isLoading || me.isLoading ? (
        <Skeleton className="h-48 rounded-xl" />
      ) : (
        <div className="rounded-xl border bg-card p-6 space-y-5">
          <div>
            <div className="flex justify-between text-sm mb-2">
              <span className="font-medium">Overall</span>
              <span>{progress.data?.overallCompletion ?? 0}%</span>
            </div>
            <Progress value={progress.data?.overallCompletion ?? 0} />
          </div>
          {(progress.data?.skills ?? []).map((s) => (
            <div key={s.skillName}>
              <div className="flex justify-between text-sm mb-1">
                <span className="capitalize">{s.skillName.replace(/_/g, " ")}</span>
                <span>{s.percentage}%</span>
              </div>
              <Progress value={s.percentage} />
            </div>
          ))}
        </div>
      )}
    </LearnerLayout>
  );
}
