import { useState } from "react";
import { Link } from "wouter";
import { PublicLayout } from "@/components/layout/public-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { toast } from "sonner";
import { request } from "@/lib/ops-api";
import { HttpError, errorMessageWithReference } from "@/lib/errors";
import { useT } from "@/i18n/use-locale";

export default function DataRequestPage() {
  const t = useT();
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
      await request("/api/data-requests", {
        method: "POST",
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
      setDone(true);
    } catch (err) {
      if (err instanceof HttpError && err.status === 429) toast.error(t("dataRequest.tooMany"));
      else toast.error(errorMessageWithReference(err, t("dataRequest.submitFailed")));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <PublicLayout>
      <div className="container mx-auto px-4 py-12 max-w-lg">
        <h1 className="text-2xl font-bold mb-2">{t("dataRequest.title")}</h1>
        <p className="text-sm text-muted-foreground mb-6">
          {t("dataRequest.intro")}{" "}
          <Link href="/privacy" className="underline text-primary">{t("dataRequest.privacyLink")}</Link>.
        </p>

        {done ? (
          <div role="status" className="rounded-xl border bg-card p-6 text-sm" data-testid="state-request-sent">
            <p className="font-medium mb-2">{t("dataRequest.sentTitle")}</p>
            <p className="text-muted-foreground">{t("dataRequest.sentBody")}</p>
          </div>
        ) : (
          <form onSubmit={submit} data-testid="form-data-request" className="space-y-4 rounded-xl border bg-card p-6">
            <div>
              <Label htmlFor="name">{t("dataRequest.fullName")}</Label>
              <Input id="name" data-testid="input-dr-name" required value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div>
              <Label htmlFor="email">{t("dataRequest.email")}</Label>
              <Input id="email" data-testid="input-dr-email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
            <div>
              <Label htmlFor="type">{t("dataRequest.type")}</Label>
              <select
                data-testid="select-request-type"
                id="type"
                className="w-full h-9 rounded-md border bg-background px-2 text-sm"
                value={requestType}
                onChange={(e) => setRequestType(e.target.value as typeof requestType)}
              >
                <option value="access">{t("dataRequest.typeAccess")}</option>
                <option value="correction">{t("dataRequest.typeCorrection")}</option>
                <option value="deletion">{t("dataRequest.typeDeletion")}</option>
                <option value="nomination">{t("dataRequest.typeNomination")}</option>
                <option value="other">{t("dataRequest.typeOther")}</option>
              </select>
            </div>
            {(requestType === "nomination" || nomineeName) && (
              <div className="space-y-3 rounded-lg border p-3 bg-muted/20">
                <p className="text-xs font-medium">{t("dataRequest.nomineeHeading")}</p>
                <div>
                  <Label htmlFor="nomineeName">{t("dataRequest.nomineeName")}</Label>
                  <Input id="nomineeName" value={nomineeName} onChange={(e) => setNomineeName(e.target.value)} required={requestType === "nomination"} />
                </div>
                <div>
                  <Label htmlFor="nomineeEmail">{t("dataRequest.nomineeEmail")}</Label>
                  <Input id="nomineeEmail" type="email" value={nomineeEmail} onChange={(e) => setNomineeEmail(e.target.value)} />
                </div>
                <div>
                  <Label htmlFor="nomineeRelation">{t("dataRequest.nomineeRelation")}</Label>
                  <Input id="nomineeRelation" value={nomineeRelation} onChange={(e) => setNomineeRelation(e.target.value)} placeholder={t("dataRequest.nomineeRelationPh")} />
                </div>
              </div>
            )}
            <div>
              <Label htmlFor="details">{t("dataRequest.details")}</Label>
              <textarea
                id="details"
                className="w-full min-h-[100px] rounded-md border bg-background px-3 py-2 text-sm"
                value={details}
                onChange={(e) => setDetails(e.target.value)}
                placeholder={t("dataRequest.detailsPh")}
              />
            </div>
            <Button type="submit" data-testid="button-submit-data-request" disabled={submitting} className="w-full">
              {submitting ? t("dataRequest.submitting") : t("dataRequest.submit")}
            </Button>
          </form>
        )}
      </div>
    </PublicLayout>
  );
}
