import { useState } from "react";
import { Link } from "wouter";
import { PublicLayout } from "@/components/layout/public-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";

const API_BASE =
  (import.meta.env.VITE_API_BASE_URL as string | undefined)
    ?.replace(/\/+$/, "")
    .replace(/\/api$/i, "") ?? "";

export default function DataRequestPage() {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [requestType, setRequestType] = useState<
    "access" | "correction" | "deletion" | "nomination" | "other"
  >("access");
  const [details, setDetails] = useState("");
  const [nomineeName, setNomineeName] = useState("");
  const [nomineeEmail, setNomineeEmail] = useState("");
  const [nomineeRelation, setNomineeRelation] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await fetch(`${API_BASE}/api/data-requests`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          name,
          email,
          requestType,
          details,
          nomineeName: nomineeName || undefined,
          nomineeEmail: nomineeEmail || undefined,
          nomineeRelation: nomineeRelation || undefined,
        }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || `Request failed (${res.status})`);
      }
      setDone(true);
      toast.success("Request submitted");
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Could not submit");
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <PublicLayout>
      <div className="container mx-auto px-4 py-12 max-w-lg">
        <h1 className="text-2xl font-bold mb-2">Data rights request</h1>
        <p className="text-sm text-muted-foreground mb-6">
          Request access, correction, deletion, or nominate a person who may exercise your rights
          (e.g. incapacity). Target response: within 90 days after identity verification. See{" "}
          <Link href="/privacy" className="underline text-primary">Privacy Policy</Link>.
        </p>

        {done ? (
          <div className="rounded-xl border bg-card p-6 text-sm">
            <p className="font-medium mb-2">Thank you</p>
            <p className="text-muted-foreground">
              Your request was recorded. We may contact you at the email provided to verify identity
              before fulfilling the request. Grievance: privacy@driveiq.in
            </p>
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-4 rounded-xl border bg-card p-6">
            <div>
              <Label htmlFor="name">Full name</Label>
              <Input id="name" required value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div>
              <Label htmlFor="email">Email used on DriveIQ</Label>
              <Input id="email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
            <div>
              <Label htmlFor="type">Request type</Label>
              <select
                id="type"
                className="w-full h-9 rounded-md border bg-background px-2 text-sm"
                value={requestType}
                onChange={(e) => setRequestType(e.target.value as typeof requestType)}
              >
                <option value="access">Access / export my data</option>
                <option value="correction">Correct my data</option>
                <option value="deletion">Delete my data</option>
                <option value="nomination">Nominate a person for my rights</option>
                <option value="other">Other / grievance</option>
              </select>
            </div>
            {(requestType === "nomination" || nomineeName) && (
              <div className="space-y-3 rounded-lg border p-3 bg-muted/20">
                <p className="text-xs font-medium">Nominee details</p>
                <div>
                  <Label htmlFor="nomineeName">Nominee full name</Label>
                  <Input id="nomineeName" value={nomineeName} onChange={(e) => setNomineeName(e.target.value)} required={requestType === "nomination"} />
                </div>
                <div>
                  <Label htmlFor="nomineeEmail">Nominee email</Label>
                  <Input id="nomineeEmail" type="email" value={nomineeEmail} onChange={(e) => setNomineeEmail(e.target.value)} />
                </div>
                <div>
                  <Label htmlFor="nomineeRelation">Relationship</Label>
                  <Input id="nomineeRelation" value={nomineeRelation} onChange={(e) => setNomineeRelation(e.target.value)} placeholder="e.g. spouse, parent" />
                </div>
              </div>
            )}
            <div>
              <Label htmlFor="details">Details (optional)</Label>
              <textarea
                id="details"
                className="w-full min-h-[100px] rounded-md border bg-background px-3 py-2 text-sm"
                value={details}
                onChange={(e) => setDetails(e.target.value)}
                placeholder="Account type, school name, or what needs correcting…"
              />
            </div>
            <Button type="submit" disabled={submitting} className="w-full">
              {submitting ? "Submitting…" : "Submit request"}
            </Button>
          </form>
        )}
      </div>
    </PublicLayout>
  );
}
