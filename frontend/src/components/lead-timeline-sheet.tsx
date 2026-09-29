import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { CalendarClock, MessageSquareText, Plus, Sparkles, ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Skeleton } from "@/components/ui/skeleton";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { addLeadNote, fetchLeadTimeline, type LeadTimelineEvent } from "@/lib/ops-api";
import { leadStatusLabel } from "@/lib/lead-status";

function when(iso: string | null | undefined) {
  return iso ? new Date(iso).toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }) : "";
}

function EventRow({ e }: { e: LeadTimelineEvent }) {
  if (e.type === "created") {
    return (
      <li className="flex gap-3">
        <Sparkles className="h-4 w-4 mt-0.5 text-primary shrink-0" />
        <div className="text-sm">
          Enquiry received{e.channel ? ` via ${e.channel}` : ""}
          <div className="text-xs text-muted-foreground">{when(e.at)}</div>
        </div>
      </li>
    );
  }
  if (e.type === "status") {
    return (
      <li className="flex gap-3">
        <ArrowRight className="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
        <div className="text-sm">
          {e.from ? `${leadStatusLabel(e.from)} → ` : ""}<span className="font-medium">{leadStatusLabel(e.to)}</span>
          <div className="text-xs text-muted-foreground">{[e.by, when(e.at)].filter(Boolean).join(" · ")}</div>
        </div>
      </li>
    );
  }
  return (
    <li className="flex gap-3">
      <MessageSquareText className="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
      <div className="text-sm min-w-0">
        <p className="whitespace-pre-wrap break-words">{e.body}</p>
        {e.followUpAt && (
          <div className="text-xs mt-1 inline-flex items-center gap-1 text-indigo-700 dark:text-indigo-400">
            <CalendarClock className="h-3 w-3" /> Follow up {when(e.followUpAt)}
          </div>
        )}
        <div className="text-xs text-muted-foreground">{[e.by, when(e.at)].filter(Boolean).join(" · ")}</div>
      </div>
    </li>
  );
}

/** Timeline, notes and follow-up for one lead (DIQ-706). */
export function LeadTimelineSheet({
  inquiryId,
  leadName,
  onOpenChange,
}: {
  inquiryId: number | null;
  leadName?: string;
  onOpenChange: (open: boolean) => void;
}) {
  const qc = useQueryClient();
  const [body, setBody] = useState("");
  const [followUpAt, setFollowUpAt] = useState("");

  const { data, isLoading, error } = useQuery({
    queryKey: ["lead-timeline", inquiryId],
    queryFn: () => fetchLeadTimeline(inquiryId!),
    enabled: inquiryId !== null,
  });

  const add = useMutation({
    mutationFn: () =>
      addLeadNote(inquiryId!, {
        body: body.trim(),
        ...(followUpAt ? { followUpAt: new Date(followUpAt).toISOString() } : {}),
      }),
    onSuccess: () => {
      setBody("");
      setFollowUpAt("");
      toast.success("Note added");
      void qc.invalidateQueries({ queryKey: ["lead-timeline", inquiryId] });
      // Status / follow-up / response time may have changed.
      void qc.invalidateQueries({ predicate: (q) => String(q.queryKey[0]).includes("inquiries") });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <Sheet open={inquiryId !== null} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-md overflow-y-auto">
        <SheetHeader>
          <SheetTitle>{leadName ?? "Lead"}</SheetTitle>
          <SheetDescription>
            {data ? `Status: ${leadStatusLabel(data.status)}` : "Timeline and notes"}
            {data?.nextFollowUpAt ? ` · follow up ${when(data.nextFollowUpAt)}` : ""}
          </SheetDescription>
        </SheetHeader>

        <div className="mt-4 space-y-3 rounded-xl border p-3">
          <div>
            <Label htmlFor="lead-note">Add a note</Label>
            <Textarea
              id="lead-note"
              rows={3}
              className="mt-1"
              placeholder="e.g. Called, interested in the weekend batch"
              value={body}
              onChange={(e) => setBody(e.target.value)}
            />
          </div>
          <div>
            <Label htmlFor="lead-follow-up">Follow up on (optional)</Label>
            <Input
              id="lead-follow-up"
              type="datetime-local"
              className="mt-1"
              value={followUpAt}
              onChange={(e) => setFollowUpAt(e.target.value)}
            />
          </div>
          <Button size="sm" disabled={!body.trim() || add.isPending} onClick={() => add.mutate()}>
            <Plus className="h-4 w-4 mr-1" /> {add.isPending ? "Saving…" : "Add note"}
          </Button>
        </div>

        <div className="mt-6">
          {isLoading ? (
            <div className="space-y-2">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-10" />)}</div>
          ) : error ? (
            <p className="text-sm text-destructive">{(error as Error).message}</p>
          ) : (
            <ol className="space-y-4">
              {[...(data?.events ?? [])].reverse().map((e, i) => <EventRow key={i} e={e} />)}
            </ol>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
