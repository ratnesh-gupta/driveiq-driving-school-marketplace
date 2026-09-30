import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { DocumentsPanel } from "@/components/documents-panel";
import { DrivingTestsPanel } from "@/components/learners/driving-tests-panel";
import { ProgressEditor } from "@/components/learners/progress-editor";
import { useListPackages, getListPackagesQueryKey } from "@/api-client";
import {
  assignLearner,
  getLearner,
  listInstructors,
  listLearnerAssignments,
  listVehicles,
  updateLearner,
  type LearnerDetail,
} from "@/lib/ops-api";

type Named = { id: number; name?: string; registrationNumber?: string; status?: string };

const SELECT = "w-full h-9 rounded-md border bg-background px-2 text-sm";
const STATUSES = ["active", "inactive", "completed", "suspended"] as const;
const LICENCE_STATUSES = [
  { value: "none", label: "Not applied" },
  { value: "applied", label: "Applied" },
  { value: "passed", label: "Test passed" },
  { value: "issued", label: "Licence issued" },
];

/** Profile and licence fields edited together; blanks are sent as null. */
const FORM_FIELDS = [
  "name", "mobile", "email", "gender", "dob", "address", "emergencyContact", "vehicleType",
  "startDate", "expectedCompletionDate", "learnerLicenseNumber", "licenseIssueDate",
  "licenseExpiryDate", "permanentLicenseStatus", "notes",
] as const;
type FormField = (typeof FORM_FIELDS)[number];

function toForm(l: LearnerDetail): Record<FormField, string> {
  return Object.fromEntries(FORM_FIELDS.map((f) => [f, (l[f] as string | null | undefined) ?? ""])) as Record<FormField, string>;
}

function Field({ id, label, children }: { id: string; label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1">
      <Label htmlFor={id}>{label}</Label>
      {children}
    </div>
  );
}

/** Everything a school needs about one learner in one place (DIQ-906). */
export function LearnerDetailSheet({
  learnerId,
  schoolId,
  canWrite,
  onOpenChange,
}: {
  learnerId: number | null;
  schoolId: number;
  canWrite: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const qc = useQueryClient();
  const open = learnerId !== null;
  const learner = useQuery({
    queryKey: ["learner", learnerId],
    queryFn: () => getLearner(learnerId!),
    enabled: open,
  });
  const [form, setForm] = useState<Record<FormField, string> | null>(null);
  useEffect(() => {
    if (learner.data) setForm(toForm(learner.data));
  }, [learner.data]);

  const instructors = useQuery({ queryKey: ["instructors", schoolId], queryFn: () => listInstructors(schoolId) as Promise<Named[]>, enabled: open });
  const vehicles = useQuery({ queryKey: ["vehicles", schoolId], queryFn: () => listVehicles(schoolId) as Promise<Named[]>, enabled: open });
  const packages = useListPackages({ schoolId }, { query: { enabled: open, queryKey: getListPackagesQueryKey({ schoolId }) } });
  const history = useQuery({ queryKey: ["learner-assignments", learnerId], queryFn: () => listLearnerAssignments(learnerId!), enabled: open });

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: ["learner", learnerId] });
    void qc.invalidateQueries({ queryKey: ["learners"] });
    void qc.invalidateQueries({ queryKey: ["learner-assignments", learnerId] });
  };

  const save = useMutation({
    mutationFn: (body: Record<string, unknown>) => updateLearner(learnerId!, body),
    onSuccess: () => {
      toast.success("Learner updated");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const assign = useMutation({
    mutationFn: (body: { instructorId?: number; vehicleId?: number }) => assignLearner(learnerId!, body),
    onSuccess: () => {
      toast.success("Assignment saved");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const l = learner.data;
  const set = (f: FormField) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    setForm((prev) => (prev ? { ...prev, [f]: e.target.value } : prev));
  const saveForm = () => {
    if (!form) return;
    save.mutate(Object.fromEntries(FORM_FIELDS.map((f) => [f, form[f].trim() === "" ? null : form[f].trim()])));
  };
  const activeInstructors = (instructors.data ?? []).filter((i) => i.status === "active" || i.id === l?.assignedInstructorId);
  const activeVehicles = (vehicles.data ?? []).filter((v) => v.status === "active" || v.id === l?.assignedVehicleId);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-2xl overflow-y-auto" data-testid="learner-detail-sheet">
        <SheetHeader>
          <SheetTitle>{l?.name ?? "Learner"}</SheetTitle>
          <SheetDescription>
            {l ? [l.status, l.packageName, l.instructorName && `Trainer: ${l.instructorName}`].filter(Boolean).join(" · ") : "Loading…"}
          </SheetDescription>
        </SheetHeader>

        {!l || !form ? (
          <Skeleton className="h-64 mt-6 rounded-xl" />
        ) : (
          <Tabs defaultValue="training" className="mt-4">
            <TabsList className="flex flex-wrap h-auto">
              <TabsTrigger value="training">Training</TabsTrigger>
              <TabsTrigger value="profile">Profile</TabsTrigger>
              <TabsTrigger value="progress">Progress</TabsTrigger>
              <TabsTrigger value="tests">Driving tests</TabsTrigger>
              <TabsTrigger value="documents">Documents</TabsTrigger>
              <TabsTrigger value="history">History</TabsTrigger>
            </TabsList>

            <TabsContent value="training" className="space-y-5 pt-2">
              <div className="grid gap-3 sm:grid-cols-2">
                <Field id="ld-status" label="Status">
                  <select id="ld-status" className={SELECT} value={l.status} disabled={!canWrite || save.isPending}
                    onChange={(e) => save.mutate({ status: e.target.value })}>
                    {STATUSES.map((s) => <option key={s} value={s} className="capitalize">{s}</option>)}
                  </select>
                </Field>
                <Field id="ld-package" label="Package">
                  <select id="ld-package" className={SELECT} value={l.packageId ?? ""} disabled={!canWrite || save.isPending}
                    onChange={(e) => save.mutate({ packageId: e.target.value ? Number(e.target.value) : null })}>
                    <option value="">No package</option>
                    {(packages.data ?? []).map((p: { id: number; name: string }) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                </Field>
                <Field id="ld-instructor" label="Trainer">
                  <select id="ld-instructor" className={SELECT} value={l.assignedInstructorId ?? ""} disabled={!canWrite || assign.isPending}
                    onChange={(e) => assign.mutate({ instructorId: e.target.value ? Number(e.target.value) : undefined, vehicleId: l.assignedVehicleId ?? undefined })}>
                    <option value="">Unassigned</option>
                    {activeInstructors.map((i) => <option key={i.id} value={i.id}>{i.name}</option>)}
                  </select>
                </Field>
                <Field id="ld-vehicle" label="Vehicle">
                  <select id="ld-vehicle" className={SELECT} value={l.assignedVehicleId ?? ""} disabled={!canWrite || assign.isPending}
                    onChange={(e) => assign.mutate({ instructorId: l.assignedInstructorId ?? undefined, vehicleId: e.target.value ? Number(e.target.value) : undefined })}>
                    <option value="">Unassigned</option>
                    {activeVehicles.map((v) => <option key={v.id} value={v.id}>{v.registrationNumber}</option>)}
                  </select>
                </Field>
              </div>
              <p className="text-xs text-muted-foreground">A newly assigned trainer is notified. Only active trainers and vehicles are listed.</p>
            </TabsContent>

            <TabsContent value="profile" className="space-y-4 pt-2">
              <fieldset disabled={!canWrite} className="grid gap-3 sm:grid-cols-2">
                <Field id="ld-name" label="Name"><Input id="ld-name" value={form.name} onChange={set("name")} /></Field>
                <Field id="ld-mobile" label="Mobile"><Input id="ld-mobile" value={form.mobile} onChange={set("mobile")} /></Field>
                <Field id="ld-email" label="Email"><Input id="ld-email" type="email" value={form.email} onChange={set("email")} /></Field>
                <Field id="ld-gender" label="Gender">
                  <select id="ld-gender" className={SELECT} value={form.gender} onChange={set("gender")}>
                    <option value="">—</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option>
                  </select>
                </Field>
                <Field id="ld-dob" label="Date of birth"><Input id="ld-dob" type="date" value={form.dob} onChange={set("dob")} /></Field>
                <Field id="ld-vt" label="Vehicle type"><Input id="ld-vt" value={form.vehicleType} onChange={set("vehicleType")} placeholder="car, bike…" /></Field>
                <Field id="ld-emergency" label="Emergency contact"><Input id="ld-emergency" value={form.emergencyContact} onChange={set("emergencyContact")} /></Field>
                <Field id="ld-start" label="Start date"><Input id="ld-start" type="date" value={form.startDate} onChange={set("startDate")} /></Field>
                <Field id="ld-expected" label="Expected completion"><Input id="ld-expected" type="date" value={form.expectedCompletionDate} onChange={set("expectedCompletionDate")} /></Field>
                <div className="sm:col-span-2"><Field id="ld-address" label="Address"><Input id="ld-address" value={form.address} onChange={set("address")} /></Field></div>
                <div className="sm:col-span-2 pt-2 font-medium text-sm">Licence</div>
                <Field id="ld-ll" label="Learner licence number"><Input id="ld-ll" value={form.learnerLicenseNumber} onChange={set("learnerLicenseNumber")} /></Field>
                <Field id="ld-pl" label="Permanent licence">
                  <select id="ld-pl" className={SELECT} value={form.permanentLicenseStatus} onChange={set("permanentLicenseStatus")}>
                    <option value="">—</option>
                    {LICENCE_STATUSES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                  </select>
                </Field>
                <Field id="ld-lli" label="Learner licence issued"><Input id="ld-lli" type="date" value={form.licenseIssueDate} onChange={set("licenseIssueDate")} /></Field>
                <Field id="ld-lle" label="Learner licence expires"><Input id="ld-lle" type="date" value={form.licenseExpiryDate} onChange={set("licenseExpiryDate")} /></Field>
                <div className="sm:col-span-2"><Field id="ld-notes" label="Notes"><Textarea id="ld-notes" rows={3} value={form.notes} onChange={set("notes")} /></Field></div>
              </fieldset>
              {canWrite && (
                <Button disabled={!form.name.trim() || save.isPending} onClick={saveForm} data-testid="button-save-learner">Save profile</Button>
              )}
            </TabsContent>

            <TabsContent value="progress" className="pt-2">
              <ProgressEditor learnerId={l.id} canEdit={canWrite} />
            </TabsContent>

            <TabsContent value="tests" className="pt-2">
              <DrivingTestsPanel learnerId={l.id} canEdit={canWrite} />
            </TabsContent>

            <TabsContent value="documents" className="pt-2">
              <DocumentsPanel kind="learner" ownerId={l.id} canReview />
            </TabsContent>

            <TabsContent value="history" className="pt-2">
              {!history.data?.length ? (
                <p className="text-sm text-muted-foreground">No trainer or vehicle assignments yet.</p>
              ) : (
                <ul className="space-y-3" data-testid="assignment-history">
                  {history.data.map((h) => (
                    <li key={h.id} className="text-sm">
                      <span className="font-medium capitalize">{h.action}</span>
                      {": "}
                      {[h.instructorName ?? "no trainer", h.vehicleRegistration ?? "no vehicle"].join(" · ")}
                      <div className="text-xs text-muted-foreground">
                        {[h.assignedBy, new Date(h.createdAt).toLocaleString()].filter(Boolean).join(" · ")}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </TabsContent>
          </Tabs>
        )}
      </SheetContent>
    </Sheet>
  );
}
