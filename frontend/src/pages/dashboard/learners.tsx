import { useState } from "react";
import { PlanGateBanner } from "@/components/plan/plan-gate-banner";
import { useEntitlements } from "@/hooks/use-entitlements";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { LearnerDetailSheet } from "@/components/learners/learner-detail-sheet";
import { useSchoolId } from "@/hooks/use-school-id";
import { createLearner, listLearnersPage } from "@/lib/ops-api";
import { Pager } from "@/components/pager";
import { ChevronRight, GraduationCap, Plus, Search } from "lucide-react";
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
  const canWrite = useEntitlements().has("learners");
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [mobile, setMobile] = useState("");
  const [email, setEmail] = useState("");
  const [openId, setOpenId] = useState<number | null>(null);

  // Paged on the server (DIQ-1305).
  const PER_PAGE = 25;
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState("");
  const { data: result, isLoading } = useQuery({
    queryKey: ["learners", schoolId, "page", page, search.trim()],
    queryFn: () => listLearnersPage<LearnerRow>(schoolId!, { page, perPage: PER_PAGE, search: search.trim() || undefined }),
    enabled: !!schoolId,
    placeholderData: (prev) => prev,
  });
  const data = result?.items;

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
      <PlanGateBanner feature="learners" />
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Learners</h1>
          <p className="text-sm text-muted-foreground mt-1">Onboard and manage active students</p>
        </div>
        <Button disabled={!canWrite} onClick={() => setOpen((v) => !v)}><Plus className="h-4 w-4 mr-1" /> Add learner</Button>
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

      <div className="relative mb-4 max-w-xs">
        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <Input value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} placeholder="Search by name or mobile" aria-label="Search learners" className="pl-8" data-testid="input-search-learners" />
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <GraduationCap className="h-10 w-10 mx-auto mb-2 opacity-30" />
          {search.trim() ? `No learners match "${search.trim()}".` : "No learners yet. Convert a lead or add one manually."}
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((l) => (
            <button
              key={l.id}
              type="button"
              onClick={() => setOpenId(l.id)}
              className="w-full flex items-center justify-between px-5 py-3.5 text-left hover:bg-muted/40 transition-colors"
              data-testid={`row-learner-${l.id}`}
            >
              <div>
                <div className="font-medium text-sm">{l.name}</div>
                <div className="text-xs text-muted-foreground">
                  {[l.mobile, l.instructorName, l.packageName].filter(Boolean).join(" · ") || "—"}
                </div>
              </div>
              <div className="flex items-center gap-2">
                <span className="text-xs px-2 py-0.5 rounded-full bg-muted font-medium capitalize">{l.status}</span>
                <ChevronRight className="h-4 w-4 text-muted-foreground" />
              </div>
            </button>
          ))}
        </div>
      )}
      <Pager page={page} perPage={PER_PAGE} total={result?.total ?? 0} onPage={setPage} noun="learners" />
      {schoolId && (
        <LearnerDetailSheet
          learnerId={openId}
          schoolId={schoolId}
          canWrite={canWrite}
          onOpenChange={(o) => { if (!o) setOpenId(null); }}
        />
      )}
    </DashboardLayout>
  );
}
