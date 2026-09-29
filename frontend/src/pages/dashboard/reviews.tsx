import { useState } from "react";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useToast } from "@/hooks/use-toast";
import { useListReviews, getListReviewsQueryKey } from "@/api-client";
import { useMutation } from "@tanstack/react-query";
import { useSchoolId } from "@/hooks/use-school-id";
import { reportReview } from "@/lib/ops-api";
import { Star, CheckCircle2, Clock, Flag } from "lucide-react";

const REPORT_REASONS = [
  { value: "fake", label: "Fake / not a real customer" },
  { value: "abusive", label: "Abusive or offensive language" },
  { value: "wrong_school", label: "Meant for another school" },
  { value: "personal_info", label: "Contains personal information" },
  { value: "other", label: "Other" },
];

function StarRow({ rating }: { rating: number }) {
  return (
    <div className="flex gap-0.5">
      {Array.from({ length: 5 }).map((_, i) => (
        <Star key={i} className={`h-3.5 w-3.5 ${i < rating ? "fill-yellow-400 text-yellow-400" : "text-muted-foreground/20"}`} />
      ))}
    </div>
  );
}

export default function DashboardReviewsPage() {
  const { toast } = useToast();
  const schoolId = useSchoolId();
  const { data: reviews, isLoading } = useListReviews({ schoolId: schoolId! }, { query: { enabled: !!schoolId, queryKey: getListReviewsQueryKey({ schoolId: schoolId! }) } });

  const [reportingId, setReportingId] = useState<number | null>(null);
  const [reason, setReason] = useState("fake");
  const [details, setDetails] = useState("");

  const report = useMutation({
    mutationFn: () => reportReview(reportingId!, { reason, details: details || undefined }),
    onSuccess: () => {
      toast({ title: "Review reported", description: "Our moderation team will look into it." });
      setReportingId(null);
      setDetails("");
    },
    onError: (e: Error) => toast({ title: "Could not report review", description: e.message, variant: "destructive" }),
  });

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Reviews</h1>
        <p className="text-muted-foreground text-sm mt-1">
          Reviews are moderated by DriveIQ. If a review breaks the rules, report it for moderation.
        </p>
      </div>

      <div className="rounded-xl border bg-card divide-y">
        {isLoading ? (
          Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="p-5"><Skeleton className="h-16" /></div>
          ))
        ) : !reviews?.length ? (
          <div className="py-16 text-center text-muted-foreground">
            <Star className="h-8 w-8 mx-auto mb-2 opacity-30" />
            No reviews yet.
          </div>
        ) : reviews.map((review) => (
          <div key={review.id} className="flex items-start justify-between gap-4 p-5" data-testid={`row-review-${review.id}`}>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-3 mb-1.5 flex-wrap">
                <span className="font-medium text-sm">{review.authorName}</span>
                <StarRow rating={review.rating} />
                <span className="text-xs text-muted-foreground">{new Date(review.createdAt).toLocaleDateString()}</span>
                {review.approved ? (
                  <span className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                    <CheckCircle2 className="h-3 w-3" /> Published
                  </span>
                ) : (
                  <span className="flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400">
                    <Clock className="h-3 w-3" /> Awaiting moderation
                  </span>
                )}
              </div>
              <p className="text-sm text-muted-foreground leading-relaxed">{review.content}</p>
            </div>
            <Button size="sm" variant="ghost" className="h-8 text-xs flex-shrink-0" onClick={() => setReportingId(review.id)} data-testid={`button-report-review-${review.id}`}>
              <Flag className="h-3.5 w-3.5 mr-1" /> Report
            </Button>
          </div>
        ))}
      </div>

      <Dialog open={reportingId !== null} onOpenChange={(open) => !open && setReportingId(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Report review</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <Select value={reason} onValueChange={setReason}>
              <SelectTrigger data-testid="select-report-reason"><SelectValue /></SelectTrigger>
              <SelectContent>
                {REPORT_REASONS.map((r) => <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>)}
              </SelectContent>
            </Select>
            <Textarea value={details} onChange={(e) => setDetails(e.target.value)} placeholder="Anything the moderators should know (optional)" rows={3} maxLength={2000} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setReportingId(null)}>Cancel</Button>
            <Button onClick={() => report.mutate()} disabled={report.isPending} data-testid="button-report-submit">
              {report.isPending ? "Reporting…" : "Submit report"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
