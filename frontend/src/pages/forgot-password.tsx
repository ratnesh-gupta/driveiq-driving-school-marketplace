import { useState } from "react";
import { Link } from "wouter";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { forgotPasswordApi } from "@/lib/auth-api";
import { useT } from "@/i18n/use-locale";

export default function ForgotPasswordPage() {
  const t = useT();
  const [email, setEmail] = useState("");
  const [status, setStatus] = useState<"idle" | "sending" | "sent">("idle");
  const [error, setError] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setStatus("sending");
    setError(null);
    try {
      await forgotPasswordApi(email);
      setStatus("sent");
    } catch (err) {
      setError(err instanceof Error ? err.message : String(err));
      setStatus("idle");
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center p-8 bg-background">
      <div className="w-full max-w-sm">
        <Link href="/" className="font-bold text-xl text-primary">DriveIQ</Link>
        <h1 className="text-2xl font-bold mt-4">{t("auth.forgotTitle")}</h1>
        <p className="text-muted-foreground text-sm mt-1 mb-6">{t("auth.forgotSub")}</p>

        {status === "sent" ? (
          <p className="rounded-lg border bg-muted/40 px-3 py-3 text-sm" data-testid="text-reset-sent">{t("auth.resetLinkSent")}</p>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <div>
              <Label htmlFor="forgot-email">{t("auth.email")}</Label>
              <Input id="forgot-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required data-testid="input-forgot-email" />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button type="submit" className="w-full" disabled={status === "sending"} data-testid="button-forgot-submit">
              {status === "sending" ? t("auth.sending") : t("auth.sendResetLink")}
            </Button>
          </form>
        )}

        <p className="text-sm text-center mt-6">
          <Link href="/auth/login" className="text-primary hover:underline">{t("auth.backToLogin")}</Link>
        </p>
      </div>
    </div>
  );
}
