import { useQuery } from "@tanstack/react-query";
import { InstructorLayout } from "@/components/layout/instructor-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchInstructorMe } from "@/lib/ops-api";
import { User } from "lucide-react";

export default function InstructorHomePage() {
  const { data, isLoading } = useQuery({
    queryKey: ["instructor", "me"],
    queryFn: fetchInstructorMe,
  });

  const instructor = (data?.instructor ?? data) as Record<string, unknown> | undefined;

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
        <div className="rounded-xl border bg-card p-6 space-y-3">
          <div className="text-lg font-semibold">{String(instructor.name ?? "Trainer")}</div>
          <div className="grid sm:grid-cols-2 gap-3 text-sm">
            <div><span className="text-muted-foreground">Status:</span> {String(instructor.status ?? "—")}</div>
            <div><span className="text-muted-foreground">Mobile:</span> {String(instructor.mobile ?? "—")}</div>
            <div><span className="text-muted-foreground">Experience:</span> {String(instructor.yearsExperience ?? instructor.experienceYears ?? "—")} yrs</div>
            <div><span className="text-muted-foreground">Learners trained:</span> {String(instructor.totalLearnersTrained ?? 0)}</div>
          </div>
        </div>
      )}
    </InstructorLayout>
  );
}
