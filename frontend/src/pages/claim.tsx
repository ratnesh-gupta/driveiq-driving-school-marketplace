import { useState } from "react";
import { Link, useLocation, useParams } from "wouter";
import { useMutation, useQuery } from "@tanstack/react-query";
import { CheckCircle2, MailCheck, MessageSquareText } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Skeleton } from "@/components/ui/skeleton";
import { setStoredToken } from "@/lib/auth-api";
import { useAuthStore } from "@/lib/store";
import { saveRegisterConsent, setRoleConsent } from "@/lib/consent";
import { claimWithGoogle, completeClaim, declineClaim, getClaim, sendClaimCode } from "@/lib/acquisition-api";

/**
 * DIQ-1104: the owner of a listing we built opens the link from our email,
 * proves the listing's email or phone is theirs with a code, and creates
 * their owner account.
 */
export default function ClaimPage() {
  const { token = "" } = useParams<{ token: string }>();
  const [, setLocation] = useLocation();
  const hydrateAuth = useAuthStore((s) => s.hydrateAuth);

  const preview = useQuery({ queryKey: ["claim", token], queryFn: () => getClaim(token), retry: false });
  // Back from Google (DIQ-1107): a verified match comes with a one-time code.
  const [googleResult] = useState(() => {
    const q = new URLSearchParams(window.location.search);
    const result = q.get("google");
    if (result) window.history.replaceState(null, "", window.location.pathname);
    return { result, code: q.get("code") ?? "" };
  });
  const [sentTo, setSentTo] = useState<string | null>(googleResult.result === "verified" ? "Google Business Profile" : null);
  const [code, setCode] = useState(googleResult.result === "verified" ? googleResult.code : "");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [agree, setAgree] = useState({ terms: false, privacy: false, processing: false });
  const [declined, setDeclined] = useState<string | null>(null);

  const send = useMutation({
    mutationFn: (channel: "email" | "sms") => sendClaimCode(token, channel),
    onSuccess: (r) => setSentTo(r.sentTo),
  });
  const complete = useMutation({
    mutationFn: () => completeClaim(token, { code, name, email, password }),
    onSuccess: async (res) => {
      setStoredToken(res.token);
      await hydrateAuth();
      await saveRegisterConsent({ ...agree, role: "school" });
      setRoleConsent("school", useAuthStore.getState().user?.id);
      setLocation("/dashboard");
    },
  });
  const google = useMutation({
    mutationFn: () => claimWithGoogle(token),
    onSuccess: (r) => window.location.assign(r.url),
  });
  const decline = useMutation({
    mutationFn: () => declineClaim(token),
    onSuccess: (r) => setDeclined(r.message),
  });

  const listing = preview.data?.listing;
  const channels: { email?: string; sms?: string; google?: string } = preview.data?.channels ?? {};
  const canSubmit = code.length === 6 && name.trim() && email.trim() && password && agree.terms && agree.privacy && agree.processing;

  return (
    <div className="min-h-screen flex items-center justify-center p-6 bg-background">
      <div className="w-full max-w-md">
        <Link href="/" className="font-bold text-xl text-primary">DriveIQ</Link>

        {declined ? (
          <p className="mt-6 text-sm" data-testid="text-claim-declined">{declined}</p>
        ) : preview.isLoading ? (
          <Skeleton className="h-48 mt-6" />
        ) : preview.isError || !listing ? (
          <div className="mt-6 space-y-2 text-sm" data-testid="text-claim-invalid">
            <p className="text-destructive">{(preview.error as Error)?.message ?? "This claim link is not valid."}</p>
            <p className="text-muted-foreground">You can still <Link href="/register" className="underline">list your school or trainer profile for free</Link>.</p>
          </div>
        ) : (
          <>
            <h1 className="text-2xl font-bold mt-4" data-testid="text-claim-title">Claim {listing.name}</h1>
            <p className="text-sm text-muted-foreground mt-1">
              We prepared a free {listing.type === "trainer" ? "trainer profile" : "school listing"} for you
              {listing.locality ? ` in ${listing.locality}` : ""}. Claim it to answer enquiries from learners. It stays hidden until you do.
            </p>
            {listing.address && <p className="text-xs text-muted-foreground mt-2">{listing.address}</p>}

            <div className="mt-6 space-y-3">
              <p className="text-sm font-medium">1. Prove it is yours</p>
              {!Object.keys(channels).length ? (
                <p className="text-sm text-muted-foreground">We have no email or phone on this listing. Reply to our email and we will help.</p>
              ) : (
                <div className="flex flex-wrap gap-2">
                  {channels.email && (
                    <Button variant="outline" size="sm" disabled={send.isPending} onClick={() => send.mutate("email")} data-testid="button-code-email">
                      <MailCheck className="h-4 w-4 mr-1" /> Email a code to {channels.email}
                    </Button>
                  )}
                  {channels.google && (
                    <Button variant="outline" size="sm" disabled={google.isPending} onClick={() => google.mutate()} data-testid="button-code-google">
                      Prove with Google Business Profile
                    </Button>
                  )}
                  {channels.sms && (
                    <Button variant="outline" size="sm" disabled={send.isPending} onClick={() => send.mutate("sms")} data-testid="button-code-sms">
                      <MessageSquareText className="h-4 w-4 mr-1" /> Text a code to {channels.sms}
                    </Button>
                  )}
                </div>
              )}
              {send.isError && <p className="text-xs text-destructive">{(send.error as Error).message}</p>}
              {googleResult.result === "nomatch" && (
                <p className="text-xs text-destructive" data-testid="text-google-nomatch">That Google account does not manage a verified profile for this business. Try another account or use a code.</p>
              )}
              {googleResult.result === "verified" && <p className="text-xs text-green-700 dark:text-green-400">Google confirmed you manage this business. The code is filled in for you.</p>}
              {sentTo && sentTo !== "Google Business Profile" && <p className="text-xs text-green-700 dark:text-green-400 flex items-center gap-1"><CheckCircle2 className="h-3.5 w-3.5" /> Code sent to {sentTo}. It works for 10 minutes.</p>}
            </div>

            {sentTo && (
              <form className="mt-6 space-y-3" onSubmit={(e) => { e.preventDefault(); if (canSubmit) complete.mutate(); }}>
                <p className="text-sm font-medium">2. Create your owner account</p>
                <div className="space-y-1">
                  <Label htmlFor="claim-code">6-digit code</Label>
                  <Input id="claim-code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))} data-testid="input-claim-code" />
                </div>
                <div className="space-y-1"><Label htmlFor="claim-name">Your name</Label><Input id="claim-name" value={name} onChange={(e) => setName(e.target.value)} data-testid="input-claim-name" /></div>
                <div className="space-y-1">
                  <Label htmlFor="claim-email">Your login email</Label>
                  <Input id="claim-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} data-testid="input-claim-email" />
                  <p className="text-[11px] text-muted-foreground">Use the address the code went to and you are confirmed straight away.</p>
                </div>
                <div className="space-y-1">
                  <Label htmlFor="claim-password">Password</Label>
                  <Input id="claim-password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} placeholder="8+ characters, upper and lower case, a number" data-testid="input-claim-password" />
                </div>
                <div className="rounded-lg border bg-muted/30 p-3 space-y-2 text-xs">
                  {([
                    ["terms", <>I agree to the <Link href="/terms" className="underline">Terms of Service</Link></>],
                    ["privacy", <>I have read the <Link href="/privacy" className="underline">Privacy Policy</Link></>],
                    ["processing", <>I consent to processing of my personal data to run my listing</>],
                  ] as const).map(([key, label]) => (
                    <label key={key} className="flex items-start gap-2 cursor-pointer">
                      <Checkbox checked={agree[key]} onCheckedChange={(v) => setAgree((a) => ({ ...a, [key]: !!v }))} className="mt-0.5" data-testid={`checkbox-claim-${key}`} />
                      <span>{label}</span>
                    </label>
                  ))}
                </div>
                {complete.isError && <p className="text-sm text-destructive">{(complete.error as Error).message}</p>}
                <Button type="submit" className="w-full" disabled={!canSubmit || complete.isPending} data-testid="button-claim-submit">
                  {complete.isPending ? "Claiming…" : "Claim my listing"}
                </Button>
              </form>
            )}

            <div className="mt-8 border-t pt-4 text-xs text-muted-foreground">
              Not your business, or do not want to be listed?{" "}
              <button
                className="underline"
                disabled={decline.isPending}
                onClick={() => window.confirm("Remove this listing and stop all emails from us?") && decline.mutate()}
                data-testid="button-claim-decline"
              >
                Remove it and stop emails
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
