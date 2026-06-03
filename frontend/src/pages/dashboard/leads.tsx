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
import { MessageCircle, Phone, Mail } from "lucide-react";
const STATUS_OPTIONS = ["pending", "contacted", "enrolled", "closed"];

function statusColor(status: string) {
  if (status === "pending") return "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400";
  if (status === "contacted") return "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400";
  if (status === "enrolled") return "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400";
  return "bg-muted text-muted-foreground";
}

export default function LeadsPage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const schoolId = useSchoolId();
  const [statusFilter, setStatusFilter] = useState("");

  const params = { schoolId: schoolId!, ...(statusFilter ? { status: statusFilter } : {}) };
  const { data: inquiries, isLoading } = useListInquiries(params, { query: { enabled: !!schoolId } });
  const updateInquiry = useUpdateInquiry();

  const handleStatusChange = (id: number, status: string) => {
    updateInquiry.mutate(
      { id, data: { status } },
      {
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: getListInquiriesQueryKey({ schoolId: schoolId! }) });
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
        <Select value={statusFilter || "all"} onValueChange={v => setStatusFilter(v === "all" ? "" : v)}>
          <SelectTrigger className="w-36" data-testid="select-status-filter">
            <SelectValue placeholder="All statuses" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            {STATUS_OPTIONS.map(s => <SelectItem key={s} value={s} className="capitalize">{s}</SelectItem>)}
          </SelectContent>
        </Select>
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
                  <td className="px-4 py-3 font-medium">{inq.name}</td>
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
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${statusColor(inq.status)}`}>
                      {inq.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">{new Date(inq.createdAt).toLocaleDateString()}</td>
                  <td className="px-4 py-3">
                    <Select
                      value={inq.status}
                      onValueChange={(v) => handleStatusChange(inq.id, v)}
                    >
                      <SelectTrigger className="h-8 w-28 text-xs" data-testid={`select-lead-status-${inq.id}`}>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {STATUS_OPTIONS.map(s => (
                          <SelectItem key={s} value={s} className="text-xs capitalize">{s}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </DashboardLayout>
  );
}
