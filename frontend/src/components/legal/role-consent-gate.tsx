import { useEffect, useState } from "react";
import { Link } from "wouter";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { useAuthStore } from "@/lib/store";
import {
  type AppRole,
  ROLE_CONSENT_VERSION,
  clearRoleConsent,
  flushPendingConsents,
  hasRoleConsent,
  hasServerRoleConsent,
  roleProcessingPurposes,
  setRoleConsent,
} from "@/lib/consent";
import { postConsents } from "@/lib/ops-api";

const ROLE_LABEL: Record<AppRole, string> = {
  school: "School partner",
  instructor: "Instructor",
  learner: "Learner",
  admin: "Platform admin",
};

/**
 * Blocks portal content until the signed-in user accepts role-specific
 * processing notice (DPDP-minded purpose transparency).
 */
export function RoleConsentGate({
  role,
  children,
}: {
  role: AppRole;
  children: React.ReactNode;
}) {
  const user = useAuthStore((s) => s.user);
  const [ready, setReady] = useState(false);
  const [open, setOpen] = useState(false);
  const [ack, setAck] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    // The browser cache lets a returning user in without a flash...
    const cached = hasRoleConsent(role, user?.id);
    setOpen(!cached);
    setReady(cached);
    if (!user) {
      setReady(true);
      return;
    }

    // ...but the server record decides (DIQ-604).
    (async () => {
      await flushPendingConsents();
      try {
        const recorded = await hasServerRoleConsent(role);
        if (cancelled) return;
        if (!recorded) clearRoleConsent(role, user.id);
        setOpen(!recorded);
      } catch {
        // API unreachable: fall back to the cached answer rather than lock the portal.
      } finally {
        if (!cancelled) setReady(true);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [role, user?.id]);

  async function accept() {
    setSaving(true);
    setSaveError(null);
    try {
      await postConsents([{ purpose: "role_portal", version: String(ROLE_CONSENT_VERSION) }]);
      setRoleConsent(role, user?.id);
      setOpen(false);
    } catch (e) {
      setSaveError(e instanceof Error ? e.message : "Could not save your consent. Please try again.");
    } finally {
      setSaving(false);
    }
  }

  if (!ready) return null;

  if (!open) return <>{children}</>;

  const purposes = roleProcessingPurposes(role);

  return (
    <>
      <div className="fixed inset-0 z-[95] flex items-center justify-center bg-black/50 p-4">
        <div
          role="dialog"
          aria-labelledby="role-consent-title"
          className="w-full max-w-lg rounded-2xl border bg-background shadow-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto"
        >
          <div>
            <h2 id="role-consent-title" className="text-lg font-semibold">
              Data use notice — {ROLE_LABEL[role]}
            </h2>
            <p className="text-sm text-muted-foreground mt-1">
              Please review how DriveQ processes personal data in this portal. You can withdraw
              optional consents and request access or deletion anytime.
            </p>
          </div>

          <ul className="text-sm space-y-2 list-disc pl-5 text-muted-foreground">
            {purposes.map((p) => (
              <li key={p}>{p}</li>
            ))}
          </ul>

          <p className="text-xs text-muted-foreground">
            Full details:{" "}
            <Link href="/privacy" className="underline text-primary">Privacy Policy</Link>
            {" · "}
            <Link href="/terms" className="underline text-primary">Terms</Link>
            {" · "}
            <Link href="/privacy/data-request" className="underline text-primary">Data rights</Link>
            {" · Grievance: privacy@driveq.in"}
          </p>

          <label className="flex items-start gap-2 text-sm cursor-pointer">
            <Checkbox checked={ack} onCheckedChange={(v) => setAck(!!v)} className="mt-0.5" />
            <span>
              I understand this notice and agree to processing of my personal data for the purposes
              listed above, as described in the Privacy Policy.
            </span>
          </label>

          {saveError && <p className="text-sm text-destructive">{saveError}</p>}

          <div className="flex justify-end gap-2 pt-1">
            <Button disabled={!ack || saving} onClick={accept}>
              {saving ? "Saving…" : "Continue to portal"}
            </Button>
          </div>
        </div>
      </div>
      {/* Keep layout mounted but not interactive underneath */}
      <div className="pointer-events-none opacity-40" aria-hidden>
        {children}
      </div>
    </>
  );
}
