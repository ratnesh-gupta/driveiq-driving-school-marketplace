import { useState } from "react";
import { Link } from "wouter";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ApiValidationError, resetPasswordApi } from "@/lib/auth-api";
import { useT } from "@/i18n/use-locale";

export default function ResetPasswordPage() {
  const t = useT();
  const params = new URLSearchParams(window.location.search);
  const token = params.get("token") ?? "";
  const email = params.get("email") ?? "";

  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [status, setStatus] = useState<"idle" | "saving" | "done">("idle");
  const [error, setError] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setStatus("saving");
    setError(null);
    try {
      await resetPasswordApi({ token, email, password, password_confirmation: confirm });
      setStatus("done");
    } catch (err) {
      const fieldMsg = err instanceof ApiValidationError ? Object.values(err.fieldErrors).flat()[0] : undefined;
      setError(fieldMsg ?? (err instanceof Error ? err.message : t("auth.resetInvalid")));
      setStatus("idle");
    }
  };

  const invalidLink = !token || !email;

  return (
    <div className="min-h-screen flex items-center justify-center p-8 bg-background">
      <div className="w-full max-w-sm">
        <Link href="/" className="font-bold text-xl text-primary">DriveIQ</Link>
        <h1 className="text-2xl font-bold mt-4 mb-6">{t("auth.forgotTitle")}</h1>

        {invalidLink ? (
          <p className="text-sm text-destructive">{t("auth.resetInvalid")}</p>
        ) : status === "done" ? (
          <div className="space-y-4">
            <p className="rounded-lg border bg-muted/40 px-3 py-3 text-sm" data-testid="text-reset-done">{t("auth.resetDone")}</p>
            <Button asChild className="w-full"><Link href="/auth/login">{t("auth.signIn")}</Link></Button>
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <p className="text-sm text-muted-foreground">{email}</p>
            <div>
              <Label htmlFor="reset-password">{t("auth.newPassword")}</Label>
              <Input id="reset-password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} data-testid="input-reset-password" />
              <p className="text-[11px] text-muted-foreground mt-1">{t("auth.passwordRule")}</p>
            </div>
            <div>
              <Label htmlFor="reset-confirm">{t("auth.confirmPassword")}</Label>
              <Input id="reset-confirm" type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} required data-testid="input-reset-confirm" />
            </div>
            {error && <p className="text-sm text-destructive">{error}</p>}
            <Button type="submit" className="w-full" disabled={status === "saving"} data-testid="button-reset-submit">
              {status === "saving" ? t("auth.resetting") : t("auth.resetBtn")}
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
