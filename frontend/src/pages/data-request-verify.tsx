import { useEffect, useState } from "react";
import { Link } from "wouter";
import { CheckCircle2, Loader2, MailWarning, TriangleAlert } from "lucide-react";
import { PublicLayout } from "@/components/layout/public-layout";
import { Button } from "@/components/ui/button";
import { request } from "@/lib/ops-api";
import { HttpError, errorReference } from "@/lib/errors";
import { takeQueryParams } from "@/lib/url-token";
import { useT } from "@/i18n/use-locale";

type Outcome =
  | { kind: "success" }
  | { kind: "invalid" }
  | { kind: "error"; reference: string };

/**
 * The token is single use, so the call is shared per token: React StrictMode's
 * double mount (and a retry click while one is in flight) reuses one request
 * instead of burning the token and showing a false "invalid".
 */
const inflight = new Map<string, Promise<Outcome>>();

function verify(token: string): Promise<Outcome> {
  const existing = inflight.get(token);
  if (existing) return existing;
  const p = request<{ verified: boolean }>("/api/data-requests/verify", {
    method: "POST",
    body: JSON.stringify({ token }),
  })
    .then((): Outcome => ({ kind: "success" }))
    .catch((err: unknown): Outcome => {
      if (err instanceof HttpError && (err.status === 422 || err.status === 404)) return { kind: "invalid" };
      return { kind: "error", reference: errorReference(err) };
    })
    .then((outcome) => {
      // Keep a success cached (a remount must not re-submit a spent token); allow retry otherwise.
      if (outcome.kind !== "success") inflight.delete(token);
      return outcome;
    });
  inflight.set(token, p);
  return p;
}

export default function DataRequestVerifyPage() {
  const t = useT();
  // Strips ?token= from the URL at once; memory keeps it across re-renders.
  const [{ token }] = useState(() => takeQueryParams("data-request-verify", ["token"]));
  const [outcome, setOutcome] = useState<Outcome | null>(null);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    if (!token) return;
    let active = true;
    setOutcome(null);
    void verify(token).then((o) => {
      if (active) setOutcome(o);
    });
    return () => {
      active = false;
    };
  }, [token, attempt]);

  return (
    <PublicLayout>
      <div className="container mx-auto px-4 py-12 max-w-lg" data-testid="page-data-request-verify">
        <h1 className="text-2xl font-bold mb-6">{t("dataRequest.verifyTitle")}</h1>

        {!token ? (
          <div role="alert" className="rounded-xl border bg-card p-6 text-sm flex gap-3" data-testid="state-verify-missing">
            <MailWarning className="h-5 w-5 shrink-0 text-amber-600" aria-hidden />
            <div>
              <p className="font-medium mb-1">{t("dataRequest.missingTitle")}</p>
              <p className="text-muted-foreground">{t("dataRequest.missingBody")}</p>
            </div>
          </div>
        ) : !outcome ? (
          <div role="status" aria-live="polite" className="rounded-xl border bg-card p-6 text-sm flex items-center gap-3" data-testid="state-verify-loading">
            <Loader2 className="h-5 w-5 animate-spin" aria-hidden />
            {t("dataRequest.verifying")}
          </div>
        ) : outcome.kind === "success" ? (
          <div role="status" className="rounded-xl border bg-card p-6 text-sm flex gap-3" data-testid="state-verify-success">
            <CheckCircle2 className="h-5 w-5 shrink-0 text-green-600" aria-hidden />
            <div>
              <p className="font-medium mb-1">{t("dataRequest.verifiedTitle")}</p>
              <p className="text-muted-foreground">{t("dataRequest.verifiedBody")}</p>
            </div>
          </div>
        ) : outcome.kind === "invalid" ? (
          <div role="alert" className="rounded-xl border border-destructive/40 bg-card p-6 text-sm space-y-4" data-testid="state-verify-invalid">
            <div className="flex gap-3">
              <TriangleAlert className="h-5 w-5 shrink-0 text-destructive" aria-hidden />
              <div>
                <p className="font-medium mb-1">{t("dataRequest.invalidTitle")}</p>
                <p className="text-muted-foreground">{t("dataRequest.invalidBody")}</p>
              </div>
            </div>
            <Button asChild variant="outline" size="sm"><Link href="/privacy/data-request">{t("dataRequest.newRequest")}</Link></Button>
          </div>
        ) : (
          <div role="alert" className="rounded-xl border border-destructive/40 bg-card p-6 text-sm space-y-4" data-testid="state-verify-error">
            <div className="flex gap-3">
              <TriangleAlert className="h-5 w-5 shrink-0 text-destructive" aria-hidden />
              <div>
                <p className="font-medium mb-1">{t("dataRequest.errorTitle")}</p>
                <p className="text-muted-foreground">{t("common.serverError")}</p>
                {outcome.reference && <p className="text-xs text-muted-foreground mt-1" data-testid="text-verify-reference">{outcome.reference}</p>}
              </div>
            </div>
            <Button size="sm" onClick={() => setAttempt((n) => n + 1)} data-testid="button-verify-retry">{t("dataRequest.retry")}</Button>
          </div>
        )}
      </div>
    </PublicLayout>
  );
}
