import { useState } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Inbox, Mail } from "lucide-react";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { listContactMessages, updateContactMessage, type ContactMessageRow } from "@/lib/ops-api";

const FILTERS: { value: string; label: string }[] = [
  { value: "", label: "All" },
  { value: "new", label: "New" },
  { value: "read", label: "Read" },
  { value: "closed", label: "Closed" },
];

const STATUS_STYLE: Record<ContactMessageRow["status"], string> = {
  new: "bg-primary/10 text-primary",
  read: "bg-muted text-muted-foreground",
  closed: "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300",
};

export default function AdminMessagesPage() {
  const qc = useQueryClient();
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);

  const { data, isLoading, error } = useQuery({
    queryKey: ["admin", "contact-messages", { status, page }],
    queryFn: () => listContactMessages({ status, page }),
    placeholderData: keepPreviousData,
  });

  const update = useMutation({
    mutationFn: ({ id, next }: { id: number; next: ContactMessageRow["status"] }) => updateContactMessage(id, next),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ["admin", "contact-messages"] }),
    onError: (e: Error) => toast.error(e.message),
  });

  const rows = data?.data ?? [];
  const meta = data?.meta;

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Contact messages</h1>
        <p className="text-muted-foreground text-sm mt-1">
          Sent from the public contact page{meta ? ` · ${meta.unread} new` : ""}
        </p>
      </div>

      <div className="flex flex-wrap gap-2 mb-4">
        {FILTERS.map((f) => (
          <Button
            key={f.value}
            size="sm"
            variant={status === f.value ? "default" : "outline"}
            onClick={() => { setStatus(f.value); setPage(1); }}
          >
            {f.label}
          </Button>
        ))}
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-24 rounded-lg" />)}</div>
      ) : error ? (
        <div className="py-12 text-center text-sm text-destructive">{(error as Error).message}</div>
      ) : !rows.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <Inbox className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No messages.
        </div>
      ) : (
        <div className="space-y-3">
          {rows.map((m) => (
            <div key={m.id} className="rounded-xl border bg-card p-4" data-testid={`contact-message-${m.id}`}>
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                  <div className="font-semibold">{m.subject}</div>
                  <div className="text-xs text-muted-foreground">
                    {m.name} · {m.email}
                    {m.createdAt ? ` · ${new Date(m.createdAt).toLocaleString()}` : ""}
                  </div>
                </div>
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${STATUS_STYLE[m.status]}`}>
                  {m.status}
                </span>
              </div>
              <p className="text-sm mt-3 whitespace-pre-wrap break-words">{m.message}</p>
              <div className="flex flex-wrap gap-2 mt-3">
                <Button size="sm" variant="outline" asChild>
                  <a href={`mailto:${m.email}?subject=${encodeURIComponent(`Re: ${m.subject}`)}`}>
                    <Mail className="h-3.5 w-3.5 mr-1" /> Reply by email
                  </a>
                </Button>
                {m.status === "new" && (
                  <Button size="sm" variant="ghost" disabled={update.isPending} onClick={() => update.mutate({ id: m.id, next: "read" })}>
                    Mark read
                  </Button>
                )}
                {m.status !== "closed" ? (
                  <Button size="sm" variant="ghost" disabled={update.isPending} onClick={() => update.mutate({ id: m.id, next: "closed" })}>
                    Close
                  </Button>
                ) : (
                  <Button size="sm" variant="ghost" disabled={update.isPending} onClick={() => update.mutate({ id: m.id, next: "read" })}>
                    Reopen
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {meta && meta.lastPage > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-muted-foreground">
          <span>page {meta.page} of {meta.lastPage}</span>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
            <Button size="sm" variant="outline" disabled={page >= meta.lastPage} onClick={() => setPage((p) => p + 1)}>Next</Button>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}
