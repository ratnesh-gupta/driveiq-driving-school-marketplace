import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/hooks/use-toast";
import { useListReviews, useUpdateReview, useDeleteReview, getListReviewsQueryKey } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import { Star, CheckCircle2, Trash2, Clock } from "lucide-react";

export default function AdminReviewsPage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const { data: reviews, isLoading } = useListReviews();
  const updateReview = useUpdateReview();
  const deleteReview = useDeleteReview();

  const approve = (id: number) => {
    updateReview.mutate({ id, data: { approved: true } }, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListReviewsQueryKey() });
        toast({ title: "Review approved" });
      },
    });
  };

  const remove = (id: number) => {
    deleteReview.mutate({ id }, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListReviewsQueryKey() });
        toast({ title: "Review deleted" });
      },
    });
  };

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Review Moderation</h1>
        <p className="text-muted-foreground text-sm mt-1">Approve or remove student reviews</p>
      </div>

      <div className="rounded-xl border bg-card divide-y">
        {isLoading ? (
          Array.from({ length: 5 }).map((_, i) => (
            <div key={i} className="p-5"><Skeleton className="h-14" /></div>
          ))
        ) : !reviews?.length ? (
          <div className="py-16 text-center text-muted-foreground">
            <Star className="h-8 w-8 mx-auto mb-2 opacity-30" />
            No reviews found.
          </div>
        ) : reviews.map((review) => (
          <div key={review.id} className="flex items-start justify-between gap-4 p-5" data-testid={`row-review-${review.id}`}>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-3 mb-1 flex-wrap">
                <span className="font-medium text-sm">{review.authorName}</span>
                <div className="flex gap-0.5">
                  {Array.from({ length: 5 }).map((_, i) => (
                    <Star key={i} className={`h-3.5 w-3.5 ${i < review.rating ? "fill-yellow-400 text-yellow-400" : "text-muted-foreground/20"}`} />
                  ))}
                </div>
                {review.schoolName && <span className="text-xs text-muted-foreground">at {review.schoolName}</span>}
                <span className="text-xs text-muted-foreground">{new Date(review.createdAt).toLocaleDateString()}</span>
                {review.approved ? (
                  <span className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                    <CheckCircle2 className="h-3 w-3" /> Approved
                  </span>
                ) : (
                  <span className="flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400">
                    <Clock className="h-3 w-3" /> Pending
                  </span>
                )}
              </div>
              <p className="text-sm text-muted-foreground leading-relaxed line-clamp-2">{review.content}</p>
            </div>
            <div className="flex gap-2 flex-shrink-0">
              {!review.approved && (
                <Button size="sm" variant="outline" className="h-8 text-xs text-green-600 border-green-200 hover:bg-green-50 dark:hover:bg-green-900/20" onClick={() => approve(review.id)} data-testid={`button-approve-${review.id}`}>
                  <CheckCircle2 className="h-3.5 w-3.5 mr-1" /> Approve
                </Button>
              )}
              <Button size="sm" variant="ghost" className="h-8 w-8 p-0 text-destructive hover:text-destructive hover:bg-destructive/10" onClick={() => remove(review.id)} data-testid={`button-delete-${review.id}`}>
                <Trash2 className="h-3.5 w-3.5" />
              </Button>
            </div>
          </div>
        ))}
      </div>
    </AdminLayout>
  );
}
