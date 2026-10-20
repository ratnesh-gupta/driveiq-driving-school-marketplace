import { useEffect, useState } from "react";
import { Link } from "wouter";
import { useMutation, useQuery } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Skeleton } from "@/components/ui/skeleton";
import { PublicLayout } from "@/components/layout/public-layout";
import { getInquiryReview, submitInquiryReview } from "@/lib/ops-api";
import { Star } from "lucide-react";
import { useT } from "@/i18n/use-locale";

/** Landing page for the one-time review link in an enquiry confirmation email (DIQ-407). */
export default function ReviewViaLinkPage() {
  const t = useT();
  const token = new URLSearchParams(window.location.search).get("token") ?? "";
  const [authorName, setAuthorName] = useState("");
  const [rating, setRating] = useState(5);
  const [content, setContent] = useState("");

  const preview = useQuery({
    queryKey: ["inquiry-review", token],
    queryFn: () => getInquiryReview(token),
    enabled: !!token,
    retry: false,
  });

  useEffect(() => {
    if (preview.data && !authorName) setAuthorName(preview.data.authorName);
  }, [preview.data, authorName]);

  const submit = useMutation({
    mutationFn: () => submitInquiryReview({ token, authorName, rating, content }),
  });

  return (
    <PublicLayout>
      <div className="max-w-lg mx-auto px-4 py-12">
        {!token || preview.isError ? (
          <div className="text-center space-y-3">
            <h1 className="text-xl font-bold">{t("reviewLink.invalidTitle")}</h1>
            <p className="text-sm text-muted-foreground">{t("reviewLink.invalidBody")}</p>
            <Button asChild variant="outline"><Link href="/search">{t("reviewLink.browse")}</Link></Button>
          </div>
        ) : preview.isLoading || !preview.data ? (
          <Skeleton className="h-64" />
        ) : submit.isSuccess ? (
          <div className="text-center space-y-3" data-testid="text-review-submitted">
            <h1 className="text-xl font-bold">{t("reviewLink.thanksTitle")}</h1>
            <p className="text-sm text-muted-foreground">{t("reviewLink.thanksBody", { school: preview.data.schoolName })}</p>
            <Button asChild variant="outline"><Link href={`/school/${preview.data.schoolSlug}`}>{t("reviewLink.back", { school: preview.data.schoolName })}</Link></Button>
          </div>
        ) : (
          <form onSubmit={(e) => { e.preventDefault(); submit.mutate(); }} className="rounded-xl border bg-card p-6 space-y-4">
            <div>
              <h1 className="text-xl font-bold">{t("reviewLink.title", { school: preview.data.schoolName })}</h1>
              <p className="text-sm text-muted-foreground mt-1">{t("reviewLink.sub")}</p>
            </div>
            <div>
              <Label htmlFor="review-name">{t("reviewLink.name")}</Label>
              <Input id="review-name" required maxLength={255} value={authorName} onChange={(e) => setAuthorName(e.target.value)} data-testid="input-link-review-name" />
            </div>
            <div>
              <Label>{t("reviewLink.rating")}</Label>
              <div className="flex gap-1 mt-1" role="radiogroup" aria-label={t("reviewLink.rating")}>
                {[1, 2, 3, 4, 5].map((n) => (
                  <button key={n} type="button" role="radio" aria-checked={rating === n} aria-label={`${n} star${n > 1 ? "s" : ""}`} onClick={() => setRating(n)}>
                    <Star className={`h-7 w-7 ${n <= rating ? "fill-yellow-400 text-yellow-400" : "text-muted-foreground/30"}`} />
                  </button>
                ))}
              </div>
            </div>
            <div>
              <Label htmlFor="review-content">{t("reviewLink.content")}</Label>
              <Textarea id="review-content" required maxLength={5000} rows={5} value={content} onChange={(e) => setContent(e.target.value)} data-testid="textarea-link-review" />
            </div>
            {submit.isError && <p className="text-sm text-destructive">{(submit.error as Error).message}</p>}
            <Button type="submit" className="w-full" disabled={submit.isPending} data-testid="button-link-review-submit">
              {submit.isPending ? t("reviewLink.submitting") : t("reviewLink.submit")}
            </Button>
          </form>
        )}
      </div>
    </PublicLayout>
  );
}
