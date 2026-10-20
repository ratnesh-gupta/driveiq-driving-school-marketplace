import { useState } from "react";
import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchAuditLogs, type AuditEntry } from "@/lib/ops-api";

const SELECT = "h-9 rounded-md border bg-background px-2 text-sm";
const MODEL_TYPES = ["School", "Inquiry", "Learner", "LearnerDocument", "Instructor", "InstructorDocument", "Schedule", "LeaveRequest", "Vehicle", "Payment", "TrainingProgress"];
const ACTIONS = ["create", "update", "delete", "assign", "convert", "attendance", "review", "mark_paid", "mark_failed", "document_verified", "document_rejected"];

function show(v: unknown): string {
  if (v === null || v === undefined || v === "") return "—";
  if (typeof v === "object") return JSON.stringify(v);
  return String(v);
}

/** Old → new values of one entry, one line per field. */
function Changes({ e }: { e: AuditEntry }) {
  const keys = Array.from(new Set([...Object.keys(e.oldValues ?? {}), ...Object.keys(e.newValues ?? {})]));
  if (!keys.length) return <span className="text-muted-foreground">—</span>;
  return (
    <ul className="space-y-0.5">
      {keys.slice(0, 6).map((k) => (
        <li key={k} className="break-words">
          <span className="text-muted-foreground">{k}:</span>{" "}
          {e.oldValues && k in e.oldValues && <><span className="line-through opacity-60">{show(e.oldValues[k])}</span>{" → "}</>}
          <span>{show(e.newValues?.[k])}</span>
        </li>
      ))}
      {keys.length > 6 && <li className="text-muted-foreground">+{keys.length - 6} more</li>}
    </ul>
  );
}

/** Filterable, paginated audit trail for a school (owner) or the platform (admin). */
export function AuditLogTable({ scope }: { scope: { schoolId: number } | "admin" }) {
  const [action, setAction] = useState("");
  const [modelType, setModelType] = useState("");
  const [page, setPage] = useState(1);
  const admin = scope === "admin";

  const { data, isLoading, error } = useQuery({
    queryKey: ["audit-logs", admin ? "admin" : scope.schoolId, action, modelType, page],
    queryFn: () => fetchAuditLogs(scope, { action, modelType, page }),
    placeholderData: keepPreviousData,
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        <select aria-label="Record type" className={SELECT} value={modelType} onChange={(e) => { setModelType(e.target.value); setPage(1); }}>
          <option value="">All records</option>
          {MODEL_TYPES.map((m) => <option key={m} value={m}>{m}</option>)}
        </select>
        <select aria-label="Action" className={SELECT} value={action} onChange={(e) => { setAction(e.target.value); setPage(1); }}>
          <option value="">All actions</option>
          {ACTIONS.map((a) => <option key={a} value={a}>{a.replace(/_/g, " ")}</option>)}
        </select>
      </div>

      {error ? (
        <p className="text-sm text-destructive">{(error as Error).message}</p>
      ) : isLoading ? (
        <Skeleton className="h-64 rounded-xl" />
      ) : !data?.data.length ? (
        <p className="text-sm text-muted-foreground py-8 text-center">No matching entries.</p>
      ) : (
        <div className="rounded-xl border bg-card overflow-x-auto">
          <table className="w-full text-sm" data-testid="audit-table">
            <thead className="bg-muted/40">
              <tr>
                {["When", "Who", admin ? "School" : null, "What", "Changes", admin ? "IP" : null].filter(Boolean).map((h) => (
                  <th key={h} className="text-left px-4 py-2.5 text-xs font-semibold text-muted-foreground whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y align-top">
              {data.data.map((e) => (
                <tr key={e.id}>
                  <td className="px-4 py-2.5 whitespace-nowrap text-muted-foreground">{new Date(e.createdAt).toLocaleString()}</td>
                  <td className="px-4 py-2.5 whitespace-nowrap">{e.userName ?? "System"}{e.userRole && <div className="text-xs text-muted-foreground">{e.userRole}</div>}</td>
                  {admin && <td className="px-4 py-2.5">{e.schoolName ?? "—"}</td>}
                  <td className="px-4 py-2.5 whitespace-nowrap"><span className="font-medium">{e.action.replace(/_/g, " ")}</span> {e.modelType}{e.modelId ? ` #${e.modelId}` : ""}</td>
                  <td className="px-4 py-2.5 text-xs max-w-md"><Changes e={e} /></td>
                  {admin && <td className="px-4 py-2.5 text-xs text-muted-foreground">{e.ipAddress ?? "—"}</td>}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {data && data.meta.lastPage > 1 && (
        <div className="flex items-center justify-between text-sm">
          <span className="text-muted-foreground">Page {data.meta.page} of {data.meta.lastPage} · {data.meta.total} entries</span>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
            <Button size="sm" variant="outline" disabled={page >= data.meta.lastPage} onClick={() => setPage((p) => p + 1)}>Next</Button>
          </div>
        </div>
      )}
    </div>
  );
}
