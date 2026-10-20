import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import { Skeleton } from "@/components/ui/skeleton";
import { Slider } from "@/components/ui/slider";
import { fetchLearnerProgress, listTrainingSkills, updateLearnerProgress } from "@/lib/ops-api";

/** Per-skill progress with an editor for staff and assigned trainers (DIQ-906 / DIQ-908). */
export function ProgressEditor({ learnerId, canEdit }: { learnerId: number; canEdit: boolean }) {
  const qc = useQueryClient();
  const progress = useQuery({ queryKey: ["progress", learnerId], queryFn: () => fetchLearnerProgress(learnerId) });
  const skills = useQuery({ queryKey: ["training-skills"], queryFn: listTrainingSkills, staleTime: Infinity });
  const [draft, setDraft] = useState<Record<string, number>>({});

  useEffect(() => {
    if (progress.data) setDraft(Object.fromEntries(progress.data.skills.map((s) => [s.skillName, s.percentage])));
  }, [progress.data]);

  const save = useMutation({
    mutationFn: (skillName: string) => updateLearnerProgress(learnerId, { skillName, percentage: draft[skillName] ?? 0 }),
    onSuccess: (data) => {
      qc.setQueryData(["progress", learnerId], data);
      toast.success("Progress saved");
    },
    onError: (e: Error) => toast.error(e.message),
  });

  if (progress.isLoading) return <Skeleton className="h-48 rounded-xl" />;
  const labels = Object.fromEntries((skills.data?.skills ?? []).map((s) => [s.code, s.label]));
  const stored = Object.fromEntries((progress.data?.skills ?? []).map((s) => [s.skillName, s.percentage]));

  return (
    <div className="space-y-4" data-testid="progress-editor">
      <div>
        <div className="flex justify-between text-sm mb-1">
          <span className="font-medium">Overall</span>
          <span>{progress.data?.overallCompletion ?? 0}%</span>
        </div>
        <Progress value={progress.data?.overallCompletion ?? 0} />
      </div>
      {(progress.data?.skills ?? []).map((s) => {
        const value = draft[s.skillName] ?? s.percentage;
        const dirty = value !== stored[s.skillName];
        return (
          <div key={s.skillName} className="space-y-1.5">
            <div className="flex justify-between text-sm">
              <span>{labels[s.skillName] ?? s.skillName}</span>
              <span className="tabular-nums text-muted-foreground">{value}%</span>
            </div>
            {canEdit ? (
              <div className="flex items-center gap-3">
                <Slider
                  value={[value]}
                  step={5}
                  max={100}
                  onValueChange={([v]) => setDraft((d) => ({ ...d, [s.skillName]: v }))}
                  aria-label={labels[s.skillName] ?? s.skillName}
                />
                <Button
                  size="sm"
                  variant={dirty ? "default" : "ghost"}
                  disabled={!dirty || save.isPending}
                  onClick={() => save.mutate(s.skillName)}
                  data-testid={`save-skill-${s.skillName}`}
                >
                  Save
                </Button>
              </div>
            ) : (
              <Progress value={value} />
            )}
          </div>
        );
      })}
    </div>
  );
}
