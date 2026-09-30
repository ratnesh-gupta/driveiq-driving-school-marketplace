import { useQuery } from "@tanstack/react-query";
import { LearnerLayout } from "@/components/layout/learner-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { ProgressEditor } from "@/components/learners/progress-editor";
import { fetchLearnerMe } from "@/lib/ops-api";

export default function LearnerProgressPage() {
  const me = useQuery({ queryKey: ["learner", "me"], queryFn: fetchLearnerMe });
  const learnerId = (me.data?.learner as { id?: number } | undefined)?.id;

  return (
    <LearnerLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Training progress</h1>
        <p className="text-sm text-muted-foreground mt-1">Your trainer updates each skill after your sessions</p>
      </div>

      {me.isLoading ? (
        <Skeleton className="h-48 rounded-xl" />
      ) : !learnerId ? (
        <p className="text-sm text-muted-foreground">Progress appears once a school enrols you.</p>
      ) : (
        <div className="rounded-xl border bg-card p-6">
          <ProgressEditor learnerId={learnerId} canEdit={false} />
        </div>
      )}
    </LearnerLayout>
  );
}
