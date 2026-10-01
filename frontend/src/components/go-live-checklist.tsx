import { useMutation } from "@tanstack/react-query";
import { Link } from "wouter";
import { toast } from "sonner";
import { CircleAlert, MailCheck, Rocket } from "lucide-react";
import { Button } from "@/components/ui/button";
import { resendVerificationEmail, type PublishBlocker } from "@/lib/ops-api";

const STEPS: Record<Exclude<PublishBlocker, "verify_email">, string> = {
  phone: "Add a phone number learners can call",
  locality: "Choose your locality",
  location: "Set your location on the map",
};

/**
 * Shown until a listing is live (DIQ-1101/1102): what still keeps it off the
 * marketplace, each with a way to fix it.
 */
export function GoLiveChecklist({
  status,
  blockers,
}: {
  status: "unclaimed" | "draft" | "published" | "suspended";
  blockers: PublishBlocker[];
}) {
  const resend = useMutation({
    mutationFn: resendVerificationEmail,
    onSuccess: () => toast.success("Verification email sent. Check your inbox."),
    onError: (e: Error) => toast.error(e.message),
  });

  if (status === "published") return null;

  if (status === "suspended") {
    return (
      <div className="rounded-xl border border-destructive/40 bg-destructive/5 p-5 mb-6 text-sm" data-testid="listing-suspended">
        <p className="font-semibold flex items-center gap-2"><CircleAlert className="h-4 w-4 text-destructive" /> Your listing is hidden</p>
        <p className="text-muted-foreground mt-1">
          Our team has taken it off the marketplace. Write to us from the <Link href="/contact" className="underline">contact page</Link> and we will explain why and what to do.
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800 p-5 mb-6" data-testid="go-live-checklist">
      <p className="font-semibold flex items-center gap-2"><Rocket className="h-4 w-4" /> Not live yet</p>
      <p className="text-sm text-muted-foreground mt-1">Learners will find you as soon as these are done. It goes live by itself.</p>
      <ul className="mt-3 space-y-2 text-sm">
        {blockers.includes("verify_email") && (
          <li className="flex flex-wrap items-center gap-2" data-testid="blocker-verify_email">
            <MailCheck className="h-4 w-4" /> Confirm your email address (we sent you a link)
            <Button size="sm" variant="outline" disabled={resend.isPending} onClick={() => resend.mutate()} data-testid="button-resend-verification">
              Send it again
            </Button>
          </li>
        )}
        {(Object.keys(STEPS) as (keyof typeof STEPS)[]).filter((b) => blockers.includes(b)).map((b) => (
          <li key={b} data-testid={`blocker-${b}`}>
            <Link href="/dashboard/profile" className="underline underline-offset-2 hover:text-primary">{STEPS[b]}</Link>
          </li>
        ))}
      </ul>
    </div>
  );
}
