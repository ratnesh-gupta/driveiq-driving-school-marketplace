import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { useSchoolId } from "@/hooks/use-school-id";
import { createInstructor, listInstructors } from "@/lib/ops-api";
import { UserCog, Plus } from "lucide-react";
import { toast } from "sonner";

type InstructorRow = {
  id: number;
  name: string;
  mobile?: string;
  status: string;
  experienceYears?: number;
  publicVisible?: boolean;
};

export default function InstructorsPage() {
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [mobile, setMobile] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["instructors", schoolId],
    queryFn: () => listInstructors(schoolId!) as Promise<InstructorRow[]>,
    enabled: !!schoolId,
  });

  const create = useMutation({
    mutationFn: () => createInstructor(schoolId!, { name, mobile }),
    onSuccess: () => {
      toast.success("Instructor added");
      setOpen(false);
      setName("");
      setMobile("");
      void qc.invalidateQueries({ queryKey: ["instructors"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <DashboardLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Instructors</h1>
          <p className="text-sm text-muted-foreground mt-1">Manage trainers for your school</p>
        </div>
        <Button onClick={() => setOpen((v) => !v)}><Plus className="h-4 w-4 mr-1" /> Add instructor</Button>
      </div>

      {open && (
        <div className="rounded-xl border bg-card p-4 mb-6 grid gap-3 sm:grid-cols-2">
          <div><Label>Name</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
          <div><Label>Mobile</Label><Input value={mobile} onChange={(e) => setMobile(e.target.value)} /></div>
          <div className="sm:col-span-2">
            <Button disabled={!name || create.isPending} onClick={() => create.mutate()}>Save</Button>
          </div>
        </div>
      )}

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <UserCog className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No instructors yet.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((i) => (
            <div key={i.id} className="flex items-center justify-between px-5 py-3.5">
              <div>
                <div className="font-medium text-sm">{i.name}</div>
                <div className="text-xs text-muted-foreground">{i.mobile || "—"}</div>
              </div>
              <span className="text-xs px-2 py-0.5 rounded-full bg-muted font-medium">{i.status}</span>
            </div>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}
