import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { getStoredToken } from "@/lib/auth-api";
import { toast } from "sonner";

const API_BASE =
  (import.meta.env.VITE_API_BASE_URL as string | undefined)
    ?.replace(/\/+$/, "")
    .replace(/\/api$/i, "") ?? "";

type Row = {
  id: number;
  name: string;
  email: string;
  requestType: string;
  details?: string | null;
  status: string;
  nomineeName?: string | null;
  createdAt?: string | null;
};

async function api<T>(path: string, init?: RequestInit): Promise<T> {
  const token = getStoredToken();
  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(init?.headers || {}),
    },
  });
  if (!res.ok) throw new Error(`Request failed (${res.status})`);
  return res.json();
}

export default function AdminDataRequestsPage() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ["admin", "data-requests"],
    queryFn: () => api<Row[]>("/api/admin/data-requests"),
  });

  const update = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      api(`/api/admin/data-requests/${id}`, {
        method: "PATCH",
        body: JSON.stringify({ status }),
      }),
    onSuccess: () => {
      toast.success("Status updated");
      void qc.invalidateQueries({ queryKey: ["admin", "data-requests"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Data subject requests</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Access, correction, deletion, and nomination requests. Verify identity before fulfilling.
          Target: respond within 90 days.
        </p>
      </div>

      {isLoading ? (
        <Skeleton className="h-40 rounded-xl" />
      ) : !data?.length ? (
        <div className="rounded-xl border bg-card py-12 text-center text-muted-foreground text-sm">
          No requests yet.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((r) => (
            <div key={r.id} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div className="text-sm">
                <div className="font-medium">{r.name} · {r.email}</div>
                <div className="text-xs text-muted-foreground mt-0.5">
                  {r.requestType} · {r.status} · {r.createdAt?.slice(0, 10)}
                  {r.nomineeName ? ` · nominee: ${r.nomineeName}` : ""}
                </div>
                {r.details && <p className="text-xs mt-1 text-muted-foreground">{r.details}</p>}
              </div>
              <div className="flex flex-wrap gap-2">
                {["in_progress", "completed", "rejected"].map((s) => (
                  <Button
                    key={s}
                    size="sm"
                    variant="outline"
                    disabled={r.status === s || update.isPending}
                    onClick={() => update.mutate({ id: r.id, status: s })}
                  >
                    {s.replace("_", " ")}
                  </Button>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </AdminLayout>
  );
}
