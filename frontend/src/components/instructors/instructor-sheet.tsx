import { useEffect, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { KeyRound, Mail } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { Textarea } from "@/components/ui/textarea";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { DocumentsPanel } from "@/components/documents-panel";
import {
  createInstructor,
  removeInstructor,
  sendInstructorLogin,
  updateInstructor,
  type InstructorDetail,
} from "@/lib/ops-api";

const SELECT = "w-full h-9 rounded-md border bg-background px-2 text-sm";

const TEXT_FIELDS = [
  "name", "mobile", "email", "gender", "dob", "address", "employeeId", "joiningDate",
  "employmentType", "licenseNumber", "licenseCategory", "licenseExpiry", "bio",
] as const;
type TextField = (typeof TEXT_FIELDS)[number];

type Form = Record<TextField, string> & {
  yearsExperience: string;
  skills: string;
  languages: string;
  womenInstructor: boolean;
  publicVisible: boolean;
  createLogin: boolean;
};

function emptyForm(): Form {
  return {
    ...(Object.fromEntries(TEXT_FIELDS.map((f) => [f, ""])) as Record<TextField, string>),
    employmentType: "full_time",
    yearsExperience: "",
    skills: "",
    languages: "",
    womenInstructor: false,
    publicVisible: false,
    createLogin: false,
  };
}

function toForm(i: InstructorDetail): Form {
  return {
    ...(Object.fromEntries(TEXT_FIELDS.map((f) => [f, (i[f] as string | null | undefined) ?? ""])) as Record<TextField, string>),
    yearsExperience: i.yearsExperience ? String(i.yearsExperience) : "",
    skills: (i.skills ?? []).join(", "),
    languages: (i.languages ?? []).join(", "),
    womenInstructor: !!i.womenInstructor,
    publicVisible: !!i.publicVisible,
    createLogin: false,
  };
}

const list = (v: string) => v.split(",").map((s) => s.trim()).filter(Boolean);

function Field({ id, label, children }: { id: string; label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1">
      <Label htmlFor={id}>{label}</Label>
      {children}
    </div>
  );
}

/**
 * Create or edit a trainer (DIQ-907). `instructor` null + open = create mode.
 * Logins are never given a password here: the trainer gets an email to set one.
 */
export function InstructorSheet({
  open,
  instructor,
  schoolId,
  canWrite,
  isOwner,
  onOpenChange,
}: {
  open: boolean;
  instructor: InstructorDetail | null;
  schoolId: number;
  canWrite: boolean;
  isOwner: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const qc = useQueryClient();
  const [form, setForm] = useState<Form>(emptyForm);
  const creating = open && !instructor;

  useEffect(() => {
    if (open) setForm(instructor ? toForm(instructor) : emptyForm());
  }, [open, instructor]);

  const refresh = () => {
    void qc.invalidateQueries({ queryKey: ["instructors"] });
    void qc.invalidateQueries({ queryKey: ["instructor-performance"] });
  };

  const payload = () => ({
    ...Object.fromEntries(TEXT_FIELDS.map((f) => [f, form[f].trim() === "" ? null : form[f].trim()])),
    employmentType: form.employmentType || "full_time",
    yearsExperience: form.yearsExperience ? Number(form.yearsExperience) : 0,
    skills: list(form.skills),
    languages: list(form.languages),
    womenInstructor: form.womenInstructor,
    publicVisible: form.publicVisible,
  });

  const save = useMutation({
    mutationFn: () =>
      creating
        ? createInstructor(schoolId, { ...payload(), createLogin: form.createLogin && !!form.email.trim() })
        : updateInstructor(instructor!.id, payload()),
    onSuccess: () => {
      toast.success(creating ? (form.createLogin && form.email ? "Trainer added. Login email sent." : "Trainer added") : "Trainer updated");
      refresh();
      if (creating) onOpenChange(false);
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const status = useMutation({
    mutationFn: (next: "active" | "inactive") => updateInstructor(instructor!.id, { status: next }),
    onSuccess: (_, next) => {
      toast.success(next === "active" ? "Trainer reactivated" : "Trainer deactivated");
      refresh();
      onOpenChange(false);
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const remove = useMutation({
    mutationFn: () => removeInstructor(instructor!.id),
    onSuccess: () => {
      toast.success("Trainer removed");
      refresh();
      onOpenChange(false);
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const login = useMutation({
    mutationFn: () => sendInstructorLogin(instructor!.id, form.email.trim() || undefined),
    onSuccess: () => {
      toast.success("Email sent. The trainer sets their own password.");
      refresh();
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const set = (f: TextField | "yearsExperience" | "skills" | "languages") =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
      setForm((p) => ({ ...p, [f]: e.target.value }));

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-2xl overflow-y-auto" data-testid="instructor-sheet">
        <SheetHeader>
          <SheetTitle>{creating ? "Add trainer" : instructor?.name}</SheetTitle>
          <SheetDescription>
            {creating
              ? "Only the name is required. Add an email to give the trainer a portal login."
              : [instructor?.status, instructor?.hasLogin ? "Has portal login" : "No portal login"].join(" · ")}
          </SheetDescription>
        </SheetHeader>

        <Tabs defaultValue="profile" className="mt-4">
          <TabsList>
            <TabsTrigger value="profile">Profile</TabsTrigger>
            {!creating && <TabsTrigger value="documents">Documents</TabsTrigger>}
          </TabsList>

          <TabsContent value="profile" className="space-y-5 pt-2">
            <fieldset disabled={!canWrite} className="grid gap-3 sm:grid-cols-2">
              <Field id="in-name" label="Name"><Input id="in-name" value={form.name} onChange={set("name")} /></Field>
              <Field id="in-mobile" label="Mobile"><Input id="in-mobile" value={form.mobile} onChange={set("mobile")} /></Field>
              <Field id="in-email" label="Email"><Input id="in-email" type="email" value={form.email} onChange={set("email")} /></Field>
              <Field id="in-gender" label="Gender">
                <select id="in-gender" className={SELECT} value={form.gender} onChange={set("gender")}>
                  <option value="">—</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option>
                </select>
              </Field>
              <Field id="in-emp" label="Employment">
                <select id="in-emp" className={SELECT} value={form.employmentType} onChange={set("employmentType")}>
                  <option value="full_time">Full time</option><option value="part_time">Part time</option><option value="contract">Contract</option>
                </select>
              </Field>
              <Field id="in-joined" label="Joining date"><Input id="in-joined" type="date" value={form.joiningDate} onChange={set("joiningDate")} /></Field>
              <Field id="in-empid" label="Employee ID"><Input id="in-empid" value={form.employeeId} onChange={set("employeeId")} /></Field>
              <Field id="in-exp" label="Years of experience"><Input id="in-exp" type="number" min={0} max={60} value={form.yearsExperience} onChange={set("yearsExperience")} /></Field>
              <Field id="in-lic" label="Driving licence number"><Input id="in-lic" value={form.licenseNumber} onChange={set("licenseNumber")} /></Field>
              <Field id="in-licc" label="Licence category"><Input id="in-licc" value={form.licenseCategory} onChange={set("licenseCategory")} placeholder="LMV, MCWG…" /></Field>
              <Field id="in-lice" label="Licence expiry"><Input id="in-lice" type="date" value={form.licenseExpiry} onChange={set("licenseExpiry")} /></Field>
              <Field id="in-dob" label="Date of birth"><Input id="in-dob" type="date" value={form.dob} onChange={set("dob")} /></Field>
              <Field id="in-skills" label="Skills (comma separated)"><Input id="in-skills" value={form.skills} onChange={set("skills")} placeholder="Manual, automatic, highway" /></Field>
              <Field id="in-langs" label="Languages (comma separated)"><Input id="in-langs" value={form.languages} onChange={set("languages")} placeholder="Marathi, Hindi, English" /></Field>
              <div className="sm:col-span-2"><Field id="in-address" label="Address"><Input id="in-address" value={form.address} onChange={set("address")} /></Field></div>
              <div className="sm:col-span-2"><Field id="in-bio" label="Public bio"><Textarea id="in-bio" rows={3} value={form.bio} onChange={set("bio")} /></Field></div>
              <label className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm">
                Woman trainer
                <Switch checked={form.womenInstructor} onCheckedChange={(v) => setForm((p) => ({ ...p, womenInstructor: v }))} />
              </label>
              <label className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm">
                Show on public school page
                <Switch checked={form.publicVisible} onCheckedChange={(v) => setForm((p) => ({ ...p, publicVisible: v }))} data-testid="switch-public" />
              </label>
              {creating && (
                <label className="sm:col-span-2 flex items-center justify-between gap-3 rounded-lg border p-3 text-sm">
                  <span>Create a portal login and email the trainer a link to set their password</span>
                  <Switch checked={form.createLogin} disabled={!form.email.trim()} onCheckedChange={(v) => setForm((p) => ({ ...p, createLogin: v }))} />
                </label>
              )}
            </fieldset>

            {canWrite && (
              <div className="flex flex-wrap gap-2">
                <Button disabled={!form.name.trim() || save.isPending} onClick={() => save.mutate()} data-testid="button-save-instructor">
                  {creating ? "Add trainer" : "Save changes"}
                </Button>
                {!creating && instructor && (
                  <>
                    <Button variant="outline" disabled={login.isPending || (!instructor.hasLogin && !form.email.trim())} onClick={() => login.mutate()} data-testid="button-send-login">
                      {instructor.hasLogin ? <><Mail className="h-4 w-4 mr-1" /> Resend login email</> : <><KeyRound className="h-4 w-4 mr-1" /> Create login</>}
                    </Button>
                    {instructor.status === "active" ? (
                      <Button variant="outline" disabled={status.isPending} onClick={() => status.mutate("inactive")}>Deactivate</Button>
                    ) : instructor.status === "inactive" ? (
                      <Button variant="outline" disabled={status.isPending} onClick={() => status.mutate("active")}>Reactivate</Button>
                    ) : null}
                    {isOwner && instructor.status !== "terminated" && (
                      <Button
                        variant="ghost"
                        className="text-destructive"
                        disabled={remove.isPending}
                        onClick={() => window.confirm(`Remove ${instructor.name}? They are hidden from scheduling and the public page; history is kept.`) && remove.mutate()}
                      >
                        Remove
                      </Button>
                    )}
                  </>
                )}
              </div>
            )}
          </TabsContent>

          {!creating && instructor && (
            <TabsContent value="documents" className="pt-2">
              <DocumentsPanel kind="instructor" ownerId={instructor.id} canReview />
            </TabsContent>
          )}
        </Tabs>
      </SheetContent>
    </Sheet>
  );
}
