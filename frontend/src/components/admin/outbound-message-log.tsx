import { useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { listOutboundMessages } from "@/lib/ops-api";

const TONE: Record<string, string> = {
  sent: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  failed: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
  sending: "bg-muted text-muted-foreground",
};

/** WhatsApp / SMS attempts with masked numbers (DIQ-1006). Kept 90 days. */
export function OutboundMessageLog() {
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "outbound-messages", status, page],
    queryFn: () => listOutboundMessages({ status, page }),
    placeholderData: keepPreviousData,
  });
  const last = data?.meta.last24h ?? {};

  return (
    <section className="mt-10" data-testid="outbound-log">
      <h2 className="text-lg font-semibold">WhatsApp / SMS sent</h2>
      <p className="text-sm text-muted-foreground mt-1">
        Driver: <span className="font-mono">{data?.meta.driver ?? "…"}</span> · last 24h: {last.sent ?? 0} sent, {last.failed ?? 0} failed.
        Numbers are masked; entries are deleted after 90 days.
      </p>
      <div className="flex gap-2 my-3">
        {[["", "All"], ["sent", "Sent"], ["failed", "Failed"]].map(([v, l]) => (
          <Button key={v} size="sm" variant={status === v ? "default" : "outline"} onClick={() => { setStatus(v); setPage(1); }}>{l}</Button>
        ))}
      </div>
      {isLoading ? (
        <Skeleton className="h-32 rounded-xl" />
      ) : !data?.data.length ? (
        <p className="text-sm text-muted-foreground">Nothing sent yet.</p>
      ) : (
        <div className="rounded-xl border bg-card overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-muted/40">
              <tr>{["When", "School", "Message", "To", "Status"].map((h) => <th key={h} className="text-left px-4 py-2.5 text-xs font-semibold text-muted-foreground">{h}</th>)}</tr>
            </thead>
            <tbody className="divide-y">
              {data.data.map((m) => (
                <tr key={m.id}>
                  <td className="px-4 py-2.5 whitespace-nowrap text-muted-foreground">{new Date(m.createdAt).toLocaleString()}</td>
                  <td className="px-4 py-2.5">{m.schoolName ?? "—"}</td>
                  <td className="px-4 py-2.5 whitespace-nowrap">{m.template.replace(/_/g, " ")} <span className="text-xs text-muted-foreground">({m.channel})</span></td>
                  <td className="px-4 py-2.5 font-mono text-xs">{m.to}</td>
                  <td className="px-4 py-2.5">
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${TONE[m.status]}`}>{m.status}</span>
                    {m.error && <div className="text-xs text-muted-foreground mt-1 max-w-xs truncate" title={m.error}>{m.error}</div>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {data && data.meta.lastPage > 1 && (
        <div className="flex justify-end gap-2 mt-3">
          <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
          <Button size="sm" variant="outline" disabled={page >= data.meta.lastPage} onClick={() => setPage((p) => p + 1)}>Next</Button>
        </div>
      )}
    </section>
  );
}
