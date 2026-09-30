import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { InstructorLayout } from "@/components/layout/instructor-layout";
import { Progress } from "@/components/ui/progress";
import { Skeleton } from "@/components/ui/skeleton";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ProgressEditor } from "@/components/learners/progress-editor";
import { SessionHistory } from "@/components/learners/session-history";
import { DrivingTestsPanel } from "@/components/learners/driving-tests-panel";
import { listMyLearners, type MyLearnerRow } from "@/lib/ops-api";
import { ChevronRight, Phone, Users } from "lucide-react";

/** Trainer's roster: learners assigned to them or booked with them (DIQ-908). */
export default function InstructorLearnersPage() {
  const [open, setOpen] = useState<MyLearnerRow | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ["instructor", "learners"], queryFn: listMyLearners });

  return (
    <InstructorLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">My learners</h1>
        <p className="text-sm text-muted-foreground mt-1">Update skill progress after each session</p>
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-16 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <Users className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No learners assigned to you yet.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((l) => (
            <button key={l.id} type="button" onClick={() => setOpen(l)}
              className="w-full grid grid-cols-[1fr_auto] sm:grid-cols-[1fr_160px_auto] items-center gap-4 px-5 py-3.5 text-left hover:bg-muted/40"
              data-testid={`row-my-learner-${l.id}`}>
              <div className="min-w-0">
                <div className="font-medium text-sm">{l.name}{!l.assignedToMe && <span className="ml-2 text-xs text-muted-foreground">(session only)</span>}</div>
                <div className="text-xs text-muted-foreground truncate">
                  {[l.packageName, l.vehicleType, l.nextSessionDate && `Next: ${new Date(l.nextSessionDate).toLocaleDateString()}`, l.status !== "active" && l.status]
                    .filter(Boolean).join(" · ") || "—"}
                </div>
              </div>
              <div className="hidden sm:flex items-center gap-2">
                <Progress value={l.overallCompletion} className="h-2" />
                <span className="text-xs tabular-nums w-9 text-right">{l.overallCompletion}%</span>
              </div>
              <ChevronRight className="h-4 w-4 text-muted-foreground" />
            </button>
          ))}
        </div>
      )}

      <Sheet open={!!open} onOpenChange={(o) => { if (!o) setOpen(null); }}>
        <SheetContent className="w-full sm:max-w-xl overflow-y-auto">
          {open && (
            <>
              <SheetHeader>
                <SheetTitle>{open.name}</SheetTitle>
                <SheetDescription className="flex items-center gap-3">
                  {open.mobile && (
                    <a href={`tel:${open.mobile}`} className="inline-flex items-center gap-1 underline"><Phone className="h-3.5 w-3.5" />{open.mobile}</a>
                  )}
                  {open.packageName}
                </SheetDescription>
              </SheetHeader>
              <Tabs defaultValue="progress" className="mt-4">
                <TabsList>
                  <TabsTrigger value="progress">Progress</TabsTrigger>
                  <TabsTrigger value="sessions">Sessions</TabsTrigger>
                  <TabsTrigger value="tests">Driving tests</TabsTrigger>
                </TabsList>
                <TabsContent value="progress" className="pt-2"><ProgressEditor learnerId={open.id} canEdit /></TabsContent>
                <TabsContent value="sessions" className="pt-2"><SessionHistory learnerId={open.id} /></TabsContent>
                <TabsContent value="tests" className="pt-2"><DrivingTestsPanel learnerId={open.id} canEdit={false} /></TabsContent>
              </Tabs>
            </>
          )}
        </SheetContent>
      </Sheet>
    </InstructorLayout>
  );
}
