import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { BellRing, Mail } from "lucide-react";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { WhatsAppOptInCard } from "@/components/whatsapp-opt-in-card";
import { useSchoolId } from "@/hooks/use-school-id";
import { fetchSchoolSettings, saveSchoolSettings, type SchoolSettings } from "@/lib/ops-api";

const REMINDER_OPTIONS = [
  { value: 0, label: "Off" },
  { value: 30, label: "After 30 minutes" },
  { value: 60, label: "After 1 hour" },
  { value: 120, label: "After 2 hours" },
  { value: 240, label: "After 4 hours" },
];

const TIMEZONES = ["Asia/Kolkata", "Asia/Dubai", "Asia/Singapore", "Europe/London", "UTC"];

type Notif = SchoolSettings["notifications"];

function Row({ id, title, hint, checked, onChange, disabled }: {
  id: string; title: string; hint: string; checked: boolean; onChange: (v: boolean) => void; disabled?: boolean;
}) {
  return (
    <div className="flex items-start justify-between gap-4 py-3">
      <div>
        <Label htmlFor={id} className="text-sm font-medium">{title}</Label>
        <p className="text-xs text-muted-foreground mt-0.5">{hint}</p>
      </div>
      <Switch id={id} checked={checked} onCheckedChange={onChange} disabled={disabled} />
    </div>
  );
}

/** DIQ-707: how the school hears about leads. */
export default function SchoolSettingsPage() {
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const { data, isLoading, error } = useQuery({
    queryKey: ["school-settings", schoolId],
    queryFn: () => fetchSchoolSettings(schoolId!),
    enabled: !!schoolId,
  });

  const [notif, setNotif] = useState<Notif | null>(null);
  const [timezone, setTimezone] = useState("Asia/Kolkata");

  useEffect(() => {
    if (data) {
      setNotif(data.settings.notifications);
      setTimezone(data.settings.timezone);
    }
  }, [data]);

  const save = useMutation({
    mutationFn: () =>
      saveSchoolSettings(schoolId!, {
        notifications: {
          email: notif!.email,
          in_app: notif!.in_app,
          new_inquiry: notif!.new_inquiry,
          new_review: notif!.new_review,
          sms: notif!.sms,
          whatsapp: notif!.whatsapp,
          reminder_after_minutes: notif!.reminder_after_minutes,
        },
        timezone,
      }),
    onSuccess: (res) => {
      qc.setQueryData(["school-settings", schoolId], res);
      toast.success("Settings saved");
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const set = <K extends keyof Notif>(key: K, value: Notif[K]) => setNotif((n) => (n ? { ...n, [key]: value } : n));
  const dirty =
    !!data && !!notif &&
    (JSON.stringify(notif) !== JSON.stringify(data.settings.notifications) || timezone !== data.settings.timezone);

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Settings</h1>
        <p className="text-sm text-muted-foreground mt-1">How your school is told about new leads</p>
      </div>

      {isLoading || !notif ? (
        error ? <p className="text-sm text-destructive">{(error as Error).message}</p> : <Skeleton className="h-64 rounded-xl" />
      ) : (
        <div className="max-w-2xl space-y-6">
          <section className="rounded-xl border bg-card p-5">
            <h2 className="font-semibold flex items-center gap-2"><BellRing className="h-4 w-4" /> Lead notifications</h2>
            <div className="divide-y mt-2">
              <Row
                id="set-new-inquiry"
                title="Tell us about new enquiries"
                hint="Master switch for new-lead alerts and reminders."
                checked={notif.new_inquiry}
                onChange={(v) => set("new_inquiry", v)}
              />
              <Row
                id="set-email"
                title="By email"
                hint="Sent to the owner and every manager, with call and WhatsApp links."
                checked={notif.email}
                onChange={(v) => set("email", v)}
                disabled={!notif.new_inquiry}
              />
              <Row
                id="set-in-app"
                title="In the dashboard"
                hint="Shows in the bell menu."
                checked={notif.in_app}
                onChange={(v) => set("in_app", v)}
                disabled={!notif.new_inquiry}
              />
              <Row
                id="set-whatsapp"
                title="On WhatsApp"
                hint="Lead alerts and reminders to team members who turned on WhatsApp alerts below. Learners who opted in also get enquiry confirmations and session reminders."
                checked={notif.whatsapp}
                onChange={(v) => set("whatsapp", v)}
              />
            </div>
          </section>

          <WhatsAppOptInCard audience="staff" />

          <section className="rounded-xl border bg-card p-5">
            <h2 className="font-semibold flex items-center gap-2"><Mail className="h-4 w-4" /> Unanswered lead reminder</h2>
            <p className="text-xs text-muted-foreground mt-1">
              If nobody has contacted a new lead by then, the team gets one reminder. Schools that reply within an hour convert far more enquiries.
            </p>
            <select
              aria-label="Reminder"
              className="mt-3 h-9 w-full sm:w-64 rounded-md border bg-background px-3 text-sm"
              value={notif.reminder_after_minutes}
              disabled={!notif.new_inquiry}
              onChange={(e) => set("reminder_after_minutes", Number(e.target.value))}
            >
              {REMINDER_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          </section>

          <section className="rounded-xl border bg-card p-5">
            <h2 className="font-semibold">Time zone</h2>
            <select
              aria-label="Time zone"
              className="mt-3 h-9 w-full sm:w-64 rounded-md border bg-background px-3 text-sm"
              value={timezone}
              onChange={(e) => setTimezone(e.target.value)}
            >
              {Array.from(new Set([timezone, ...TIMEZONES])).map((tz) => <option key={tz} value={tz}>{tz}</option>)}
            </select>
          </section>

          <Button disabled={!dirty || save.isPending} onClick={() => save.mutate()} data-testid="button-save-settings">
            {save.isPending ? "Saving…" : "Save settings"}
          </Button>
        </div>
      )}
    </DashboardLayout>
  );
}
