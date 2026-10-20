import { useState } from "react";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/hooks/use-toast";
import { useListInquiries, useUpdateInquiry, getListInquiriesQueryKey } from "@/api-client";
import { useSchoolId } from "@/hooks/use-school-id";
import { useQueryClient } from "@tanstack/react-query";
import { MessageCircle, Phone, Mail, NotebookPen, CalendarClock, UserPlus } from "lucide-react";
import { LeadTimelineSheet } from "@/components/lead-timeline-sheet";
import { convertInquiry, fetchSchoolSettings } from "@/lib/ops-api";
import { useEntitlements } from "@/hooks/use-entitlements";
import { formatDuration } from "@/lib/response-time";
import { useMutation, useQuery } from "@tanstack/react-query";

/** "Awaiting reply" badge for leads nobody has answered yet (DIQ-708). */
function AwaitingBadge({ createdAt, thresholdMinutes }: { createdAt: string; thresholdMinutes: number }) {
  const waitedS = (Date.now() - new Date(createdAt).getTime()) / 1000;
  const tone =
    waitedS >= 86400
      ? "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400"
      : waitedS >= (thresholdMinutes || 60) * 60
        ? "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400"
        : "bg-muted text-muted-foreground";
  return (
    <span className={`text-xs px-2 py-0.5 rounded-full font-medium whitespace-nowrap ${tone}`} data-testid="badge-awaiting">
      Awaiting reply · {formatDuration(waitedS)}
    </span>
  );
}
import { LEAD_STATUSES, leadStatusColor, leadStatusLabel } from "@/lib/lead-status";

export default function LeadsPage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const schoolId = useSchoolId();
  const [statusFilter, setStatusFilter] = useState("");
  const [followUpDue, setFollowUpDue] = useState(false);
  const [sort, setSort] = useState<"newest" | "oldest_waiting">("newest");
  const [openLead, setOpenLead] = useState<{ id: number; name: string } | null>(null);
  const { data: settings } = useQuery({
    queryKey: ["school-settings", schoolId],
    queryFn: () => fetchSchoolSettings(schoolId!),
    enabled: !!schoolId,
  });
  const reminderAfter = settings?.settings.notifications.reminder_after_minutes ?? 60;

  const params = {
    schoolId: schoolId!,
    ...(statusFilter ? { status: statusFilter } : {}),
    ...(followUpDue ? { followUpDue: 1 } : {}),
    ...(sort !== "newest" ? { sort } : {}),
  };
  const { data: inquiries, isLoading } = useListInquiries(params, { query: { enabled: !!schoolId, queryKey: getListInquiriesQueryKey(params) } });
  const updateInquiry = useUpdateInquiry();
  // With the learner module, "Converted" is reached only by creating the learner (DIQ-905).
  const canConvert = useEntitlements().has("learners");
  const convert = useMutation({
    mutationFn: (id: number) => convertInquiry(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ predicate: (q) => String(q.queryKey[0]).includes("inquiries") });
      void queryClient.invalidateQueries({ queryKey: ["learners"] });
      toast({
        title: "Learner created",
        description: "Assign a trainer and package from the Learners page.",
      });
    },
    onError: (e: Error) => toast({ title: "Could not convert", description: e.message, variant: "destructive" }),
  });
  const handleConvert = (id: number, name: string) => {
    if (window.confirm(`Create a learner record for ${name} and mark this lead converted?`)) convert.mutate(id);
  };

  const handleStatusChange = (id: number, status: string) => {
    let lostReason: string | undefined;
    if (status === "lost") {
      const reason = window.prompt("Why was this lead lost? (optional)");
      if (reason === null) return; // cancelled
      lostReason = reason.trim() || undefined;
    }
    updateInquiry.mutate(
      { id, data: { status, ...(lostReason ? { lostReason } : {}) } },
      {
        onSuccess: () => {
          queryClient.invalidateQueries({ predicate: (q) => String(q.queryKey[0]).includes("inquiries") });
          toast({ title: "Status updated" });
        },
        onError: () => toast({ title: "Update failed", variant: "destructive" }),
      }
    );
  };

  return (
    <DashboardLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Lead Management</h1>
          <p className="text-muted-foreground text-sm mt-1">Manage and track student inquiries</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
        <Button
          variant={followUpDue ? "default" : "outline"}
          size="sm"
          onClick={() => setFollowUpDue(v => !v)}
          data-testid="button-follow-up-due"
        >
          <CalendarClock className="h-4 w-4 mr-1" /> Follow-up due
        </Button>
        <Select value={sort} onValueChange={v => setSort(v as "newest" | "oldest_waiting")}>
          <SelectTrigger className="w-40" data-testid="select-sort">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="newest">Newest first</SelectItem>
            <SelectItem value="oldest_waiting">Longest waiting</SelectItem>
          </SelectContent>
        </Select>
        <Select value={statusFilter || "all"} onValueChange={v => setStatusFilter(v === "all" ? "" : v)}>
          <SelectTrigger className="w-36" data-testid="select-status-filter">
            <SelectValue placeholder="All statuses" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            {LEAD_STATUSES.map(s => <SelectItem key={s} value={s}>{leadStatusLabel(s)}</SelectItem>)}
          </SelectContent>
        </Select>
        </div>
      </div>

      <div className="rounded-xl border bg-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b bg-muted/40">
              <tr>
                {["Student", "Contact", "Vehicle", "Message", "Status", "Date", "Action"].map(h => (
                  <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y">
              {isLoading ? (
                Array.from({ length: 4 }).map((_, i) => (
                  <tr key={i}><td colSpan={7} className="px-4 py-3"><Skeleton className="h-8" /></td></tr>
                ))
              ) : !inquiries?.length ? (
                <tr>
                  <td colSpan={7} className="text-center py-16 text-muted-foreground">
                    <MessageCircle className="h-8 w-8 mx-auto mb-2 opacity-30" />
                    No inquiries found
                  </td>
                </tr>
              ) : inquiries.map((inq) => (
                <tr key={inq.id} className="hover:bg-muted/20 transition-colors" data-testid={`row-lead-${inq.id}`}>
                  <td className="px-4 py-3 font-medium">
                    <button
                      type="button"
                      className="inline-flex items-center gap-1.5 hover:underline text-left"
                      onClick={() => setOpenLead({ id: inq.id, name: inq.name })}
                      data-testid={`button-lead-notes-${inq.id}`}
                    >
                      {inq.name}
                      <NotebookPen className="h-3.5 w-3.5 text-muted-foreground" />
                    </button>
                    {inq.nextFollowUpAt && (
                      <div className="text-xs text-indigo-700 dark:text-indigo-400 mt-0.5">
                        Follow up {new Date(inq.nextFollowUpAt).toLocaleDateString()}
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex flex-col gap-0.5">
                      <div className="flex items-center gap-1 text-muted-foreground"><Phone className="h-3 w-3" />{inq.phone}</div>
                      {inq.email && <div className="flex items-center gap-1 text-muted-foreground"><Mail className="h-3 w-3" />{inq.email}</div>}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant="secondary" className="text-xs">{inq.vehicleType}</Badge>
                  </td>
                  <td className="px-4 py-3 max-w-48 truncate text-muted-foreground">{inq.message || "—"}</td>
                  <td className="px-4 py-3">
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${leadStatusColor(inq.status)}`}>
                      {leadStatusLabel(inq.status)}
                    </span>
                    {!inq.firstRespondedAt && inq.status === "pending" && (
                      <div className="mt-1"><AwaitingBadge createdAt={inq.createdAt} thresholdMinutes={reminderAfter} /></div>
                    )}
                    {inq.status === "lost" && inq.lostReason && (
                      <div className="text-xs text-muted-foreground mt-1 max-w-40 truncate" title={inq.lostReason}>{inq.lostReason}</div>
                    )}
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">{new Date(inq.createdAt).toLocaleDateString()}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-2">
                    <Select
                      value={inq.status}
                      onValueChange={(v) => handleStatusChange(inq.id, v)}
                    >
                      <SelectTrigger className="h-8 w-28 text-xs" data-testid={`select-lead-status-${inq.id}`}>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {LEAD_STATUSES.filter(s => !canConvert || s !== "converted" || inq.status === "converted").map(s => (
                          <SelectItem key={s} value={s} className="text-xs">{leadStatusLabel(s)}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {canConvert && inq.status !== "converted" && inq.status !== "lost" && (
                      <Button
                        size="sm"
                        variant="outline"
                        className="h-8 text-xs"
                        disabled={convert.isPending}
                        onClick={() => handleConvert(inq.id, inq.name)}
                        data-testid={`button-convert-${inq.id}`}
                      >
                        <UserPlus className="h-3.5 w-3.5 mr-1" /> Convert
                      </Button>
                    )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      <LeadTimelineSheet
        inquiryId={openLead?.id ?? null}
        leadName={openLead?.name}
        onOpenChange={(open) => { if (!open) setOpenLead(null); }}
      />
    </DashboardLayout>
  );
}
