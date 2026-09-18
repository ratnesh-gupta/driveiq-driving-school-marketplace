import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useSchoolId } from "@/hooks/use-school-id";
import { createLearner, listLearners } from "@/lib/ops-api";
import { GraduationCap, Plus } from "lucide-react";
import { toast } from "sonner";

type LearnerRow = {
  id: number;
  name: string;
  mobile?: string;
  email?: string;
  status: string;
  instructorName?: string;
  packageName?: string;
  startDate?: string;
};

export default function LearnersPage() {
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [mobile, setMobile] = useState("");
  const [email, setEmail] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["learners", schoolId],
    queryFn: () => listLearners(schoolId!) as Promise<LearnerRow[]>,
    enabled: !!schoolId,
  });

  const create = useMutation({
    mutationFn: () => createLearner(schoolId!, { name, mobile, email }),
    onSuccess: () => {
      toast.success("Learner created");
      setOpen(false);
      setName("");
      setMobile("");
      setEmail("");
      void qc.invalidateQueries({ queryKey: ["learners"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <DashboardLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Learners</h1>
          <p className="text-sm text-muted-foreground mt-1">Onboard and manage active students</p>
        </div>
        <Button onClick={() => setOpen((v) => !v)}><Plus className="h-4 w-4 mr-1" /> Add learner</Button>
      </div>

      {open && (
        <div className="rounded-xl border bg-card p-4 mb-6 grid gap-3 sm:grid-cols-3">
          <div><Label>Name</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
          <div><Label>Mobile</Label><Input value={mobile} onChange={(e) => setMobile(e.target.value)} /></div>
          <div><Label>Email</Label><Input value={email} onChange={(e) => setEmail(e.target.value)} /></div>
          <div className="sm:col-span-3">
            <Button disabled={!name || create.isPending} onClick={() => create.mutate()}>Save</Button>
          </div>
        </div>
      )}

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <GraduationCap className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No learners yet. Convert a lead or add one manually.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((l) => (
            <div key={l.id} className="flex items-center justify-between px-5 py-3.5">
              <div>
                <div className="font-medium text-sm">{l.name}</div>
                <div className="text-xs text-muted-foreground">
                  {[l.mobile, l.instructorName, l.packageName].filter(Boolean).join(" · ") || "—"}
                </div>
              </div>
              <span className="text-xs px-2 py-0.5 rounded-full bg-muted font-medium">{l.status}</span>
            </div>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}
