import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useConfirm } from "@/components/confirm-dialog";
import { request } from "@/lib/ops-api";
import { fetchPage } from "@/lib/paged-api";
import { HttpError, errorMessageWithReference } from "@/lib/errors";
import { toast } from "sonner";

const PAGE_SIZE = 20;
const ERASE_WORD = "ERASE";

type Row = {
  id: number;
  name: string;
  email: string;
  requestType: string;
  details?: string | null;
  status: string;
  nomineeName?: string | null;
  userId?: number | null;
  verifiedAt?: string | null;
  dueAt?: string | null;
  erasedAt?: string | null;
  consents?: { id: number; purpose: string; role: string | null; version: string; grantedAt: string; withdrawnAt: string | null }[];
  createdAt?: string | null;
};

type EraseResult = { id: number; status: string; result: string; counts: Record<string, number>; erasedAt: string | null };

const BLOCKER_TEXT: Record<string, string> = {
  platform_admin: "The account belongs to a platform admin.",
  school_owner: "The account owns a school; transfer or close the school first.",
  legal_hold: "The account is linked to a school under legal hold.",
};

const VERIFY_REQUIRED = "The requester has not confirmed their e-mail address yet, so this cannot be done. Reject it or wait for them to confirm.";

const isClosed = (r: Row) => r.status === "completed" || r.status === "rejected";
const isOverdue = (r: Row) => !!r.dueAt && !isClosed(r) && new Date(r.dueAt).getTime() < Date.now();

export default function AdminDataRequestsPage() {
  const qc = useQueryClient();
  const { prompt } = useConfirm();
  const [page, setPage] = useState(1);
  const [blocked, setBlocked] = useState<{ id: number; blockers: string[] } | null>(null);
  const [erased, setErased] = useState<EraseResult | null>(null);

  // The backend paginates with page/perPage (and ignores limit/offset); the total is in X-Total-Count.
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ["admin", "data-requests", page],
    queryFn: ({ signal }) => fetchPage<Row>("/api/admin/data-requests", { page, perPage: PAGE_SIZE }, 0, PAGE_SIZE, signal),
    placeholderData: (prev) => prev,
  });
  const rows = data?.items ?? [];
  const total = data?.total ?? 0;
  const pageCount = Math.max(1, Math.ceil(total / PAGE_SIZE));

  const refresh = () => void qc.invalidateQueries({ queryKey: ["admin", "data-requests"] });

  const update = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      request(`/api/admin/data-requests/${id}`, { method: "PATCH", body: JSON.stringify({ status }) }),
    onSuccess: () => {
      toast.success("Status updated");
      refresh();
    },
    onError: (e: Error) => {
      if (e instanceof HttpError && e.status === 409 && e.code === "verification_required") toast.error(VERIFY_REQUIRED);
      else toast.error(errorMessageWithReference(e, "Could not update the request"));
    },
  });

  const erase = useMutation({
    mutationFn: (id: number) => request<EraseResult>(`/api/admin/data-requests/${id}/erase`, { method: "POST" }),
    onSuccess: (res) => {
      setBlocked(null);
      setErased(res);
      toast.success("User data erased");
      refresh();
    },
    onError: (e: Error, id) => {
      if (e instanceof HttpError && e.status === 409 && e.blockers) {
        setErased(null);
        setBlocked({ id, blockers: e.blockers });
      } else if (e instanceof HttpError && e.status === 409 && e.code === "verification_required") toast.error(VERIFY_REQUIRED);
      else toast.error(errorMessageWithReference(e, "Could not erase the data"));
    },
  });

  const onErase = async (r: Row) => {
    const typed = await prompt({
      title: "Erase user data",
      description: `This anonymises the account and personal data held for ${r.email}. It cannot be undone. Type ${ERASE_WORD} to confirm.`,
      input: { label: `Type ${ERASE_WORD}`, placeholder: ERASE_WORD, required: true },
      confirmLabel: "Erase data",
      destructive: true,
      testId: "dialog-erase-user-data",
    });
    if (typed === null) return;
    if (typed.trim() !== ERASE_WORD) {
      toast.error(`Nothing was erased: you must type ${ERASE_WORD} exactly.`);
      return;
    }
    erase.mutate(r.id);
  };

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Data subject requests</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Access, correction, deletion, and nomination requests. Requests only become actionable after the requester confirms their e-mail.
          Each has a due date; overdue ones are highlighted.
        </p>
      </div>

      {erased && (
        <div role="status" className="mb-4 rounded-xl border border-green-600/40 bg-green-50 dark:bg-green-950/30 p-4 text-sm" data-testid="state-erase-success">
          Request #{erased.id}: data erased ({erased.result}).{" "}
          {Object.entries(erased.counts).filter(([, n]) => n > 0).map(([k, n]) => `${k.replace(/_/g, " ")}: ${n}`).join(", ")}
        </div>
      )}

      {isLoading ? (
        <Skeleton className="h-40 rounded-xl" />
      ) : isError ? (
        <div role="alert" className="rounded-xl border border-destructive/40 bg-card py-8 text-center text-sm text-destructive" data-testid="state-requests-error">
          {errorMessageWithReference(error, "Could not load requests")}
        </div>
      ) : !rows.length ? (
        <div className="rounded-xl border bg-card py-12 text-center text-muted-foreground text-sm">
          No requests yet.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {rows.map((r) => (
            <div key={r.id} className={`p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 ${isOverdue(r) ? "bg-red-50 dark:bg-red-950/20" : ""}`} data-testid={`row-data-request-${r.id}`}>
              <div className="text-sm">
                <div className="font-medium">{r.name} · {r.email}</div>
                <div className="text-xs text-muted-foreground mt-0.5">
                  {r.requestType} · {r.status} · {r.createdAt?.slice(0, 10)}
                  {r.nomineeName ? ` · nominee: ${r.nomineeName}` : ""}
                </div>
                <div className="flex flex-wrap gap-1.5 mt-1.5" data-testid={`badges-data-request-${r.id}`}>
                  {r.verifiedAt ? (
                    <Badge variant="secondary" title={r.verifiedAt}>Verified {r.verifiedAt.slice(0, 10)}</Badge>
                  ) : (
                    <Badge variant="outline" className="border-amber-500 text-amber-700">Awaiting e-mail confirmation</Badge>
                  )}
                  {r.dueAt && (
                    <Badge variant={isOverdue(r) ? "destructive" : "outline"} title={r.dueAt}>
                      {isOverdue(r) ? "Overdue" : "Due"} {r.dueAt.slice(0, 10)}
                    </Badge>
                  )}
                  {r.erasedAt && <Badge variant="secondary" title={r.erasedAt}>Erased {r.erasedAt.slice(0, 10)}</Badge>}
                </div>
                {blocked?.id === r.id && (
                  <div role="alert" className="mt-2 rounded-md border border-destructive/40 p-2 text-xs" data-testid={`state-erase-blocked-${r.id}`}>
                    <p className="font-medium text-destructive">Erasure is blocked:</p>
                    <ul className="list-disc pl-4 mt-1">
                      {blocked.blockers.map((b) => <li key={b}>{BLOCKER_TEXT[b] ?? b}</li>)}
                    </ul>
                  </div>
                )}
                {r.details && <p className="text-xs mt-1 text-muted-foreground">{r.details}</p>}
                {r.userId ? (
                  <details className="mt-2 text-xs">
                    <summary className="cursor-pointer text-primary">
                      Consent history ({r.consents?.length ?? 0})
                    </summary>
                    {r.consents?.length ? (
                      <ul className="mt-1 space-y-0.5 text-muted-foreground">
                        {r.consents.map((c) => (
                          <li key={c.id}>
                            {c.purpose}
                            {c.role ? ` (${c.role})` : ""} v{c.version} · granted {c.grantedAt.slice(0, 10)}
                            {c.withdrawnAt ? ` · withdrawn ${c.withdrawnAt.slice(0, 10)}` : ""}
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <p className="mt-1 text-muted-foreground">No consent records for this account.</p>
                    )}
                  </details>
                ) : (
                  <p className="text-xs mt-1 text-muted-foreground">No matching account.</p>
                )}
              </div>
              <div className="flex flex-wrap gap-2">
                {["in_progress", "completed", "rejected"].map((s) => (
                  <Button
                    key={s}
                    size="sm"
                    variant="outline"
                    disabled={r.status === s || update.isPending}
                    onClick={() => update.mutate({ id: r.id, status: s })}
                    data-testid={`button-status-${s}-${r.id}`}
                  >
                    {s.replace("_", " ")}
                  </Button>
                ))}
                {r.requestType === "deletion" && !r.erasedAt && (
                  <Button
                    size="sm"
                    variant="destructive"
                    disabled={!r.verifiedAt || erase.isPending}
                    title={r.verifiedAt ? undefined : "Requester has not confirmed their e-mail"}
                    onClick={() => void onErase(r)}
                    data-testid={`button-erase-${r.id}`}
                  >
                    Erase user data
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {total > PAGE_SIZE && (
        <div className="mt-4 flex items-center justify-between text-sm" data-testid="pager-data-requests">
          <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)} data-testid="button-page-prev">Previous</Button>
          <span aria-live="polite">Page {page} of {pageCount} · {total} requests</span>
          <Button size="sm" variant="outline" disabled={page >= pageCount} onClick={() => setPage((p) => p + 1)} data-testid="button-page-next">Next</Button>
        </div>
      )}
    </AdminLayout>
  );
}
