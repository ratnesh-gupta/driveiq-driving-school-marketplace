import { useState } from "react";
import { Link, useLocation } from "wouter";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { acceptInvitation, getInvitation } from "@/lib/ops-api";
import { setStoredToken } from "@/lib/auth-api";
import { useAuthStore } from "@/lib/store";
import { useT } from "@/i18n/use-locale";

/** Public page an invited manager lands on from the email link (DIQ-403). */
export default function TeamAcceptPage() {
  const t = useT();
  const [, setLocation] = useLocation();
  const token = new URLSearchParams(window.location.search).get("token") ?? "";
  const { user, isLoggedIn, hydrateAuth } = useAuthStore();

  const [name, setName] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");

  const preview = useQuery({
    queryKey: ["invitation", token],
    queryFn: () => getInvitation(token),
    enabled: !!token,
    retry: false,
  });

  const accept = useMutation({
    mutationFn: () =>
      acceptInvitation(
        preview.data?.hasAccount
          ? { token }
          : { token, name, password, password_confirmation: confirm },
      ),
    onSuccess: async (res) => {
      if (res.token) setStoredToken(res.token);
      await hydrateAuth();
      setLocation("/dashboard");
    },
  });

  const invite = preview.data;
  const signedInAsInvitee = isLoggedIn && user?.email?.toLowerCase() === invite?.email.toLowerCase();
  const next = encodeURIComponent(`/team/accept?token=${token}`);

  return (
    <div className="min-h-screen flex items-center justify-center p-8 bg-background">
      <div className="w-full max-w-sm">
        <Link href="/" className="font-bold text-xl text-primary">DriveIQ</Link>

        {!token || preview.isError ? (
          <p className="mt-6 text-sm text-destructive" data-testid="text-invite-invalid">{t("team.acceptInvalid")}</p>
        ) : preview.isLoading || !invite ? (
          <Skeleton className="h-40 mt-6" />
        ) : (
          <>
            <h1 className="text-2xl font-bold mt-4">{t("team.acceptTitle", { school: invite.schoolName })}</h1>
            <p className="text-muted-foreground text-sm mt-1 mb-6">{t("team.acceptSub")}</p>

            {invite.hasAccount ? (
              signedInAsInvitee ? (
                <Button className="w-full" onClick={() => accept.mutate()} disabled={accept.isPending} data-testid="button-accept-invite">
                  {accept.isPending ? t("team.accepting") : t("team.accept")}
                </Button>
              ) : (
                <div className="space-y-4">
                  <p className="text-sm">
                    {isLoggedIn ? t("team.wrongAccount", { email: invite.email }) : t("team.signInFirst", { email: invite.email })}
                  </p>
                  {!isLoggedIn && (
                    <Button asChild className="w-full">
                      <Link href={`/auth/login?next=${next}`}>{t("team.signIn")}</Link>
                    </Button>
                  )}
                </div>
              )
            ) : (
              <form onSubmit={(e) => { e.preventDefault(); accept.mutate(); }} className="space-y-4">
                <p className="text-sm text-muted-foreground">{invite.email}</p>
                <div>
                  <Label htmlFor="invite-name">{t("team.name")}</Label>
                  <Input id="invite-name" required value={name} onChange={(e) => setName(e.target.value)} data-testid="input-invite-name" />
                </div>
                <div>
                  <Label htmlFor="invite-password">{t("team.password")}</Label>
                  <Input id="invite-password" type="password" required minLength={8} value={password} onChange={(e) => setPassword(e.target.value)} data-testid="input-invite-password" />
                  <p className="text-[11px] text-muted-foreground mt-1">{t("auth.passwordRule")}</p>
                </div>
                <div>
                  <Label htmlFor="invite-confirm">{t("team.confirmPassword")}</Label>
                  <Input id="invite-confirm" type="password" required value={confirm} onChange={(e) => setConfirm(e.target.value)} data-testid="input-invite-confirm" />
                </div>
                <Button type="submit" className="w-full" disabled={accept.isPending} data-testid="button-accept-invite">
                  {accept.isPending ? t("team.accepting") : t("team.accept")}
                </Button>
              </form>
            )}

            {accept.isError && <p className="mt-3 text-sm text-destructive">{(accept.error as Error).message}</p>}
          </>
        )}
      </div>
    </div>
  );
}
