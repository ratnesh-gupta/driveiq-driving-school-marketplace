import { useEffect, useState } from "react";
import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Search, Users } from "lucide-react";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { listAdminUsers, setAdminUserActive, type AdminUserRow } from "@/lib/ops-api";

const ROLES = [
  { value: "", label: "All roles" },
  { value: "admin", label: "Admin" },
  { value: "school", label: "School" },
  { value: "instructor", label: "Instructor" },
  { value: "learner", label: "Learner" },
];

const STATUSES = [
  { value: "", label: "Any status" },
  { value: "active", label: "Active" },
  { value: "deactivated", label: "Deactivated" },
];

const SELECT_CLASS = "h-9 rounded-md border bg-background px-3 text-sm";

export default function AdminUsersPage() {
  const qc = useQueryClient();
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [role, setRole] = useState("");
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);

  // Debounce typing so every keystroke doesn't hit the API.
  useEffect(() => {
    const t = setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  const { data, isLoading, error } = useQuery({
    queryKey: ["admin", "users", { search, role, status, page }],
    queryFn: () => listAdminUsers({ search, role, status, page }),
    placeholderData: keepPreviousData,
  });

  const toggle = useMutation({
    mutationFn: (u: AdminUserRow) => setAdminUserActive(u.id, !u.active),
    onSuccess: (u) => {
      toast.success(u.active ? `${u.name} reactivated` : `${u.name} deactivated and signed out`);
      void qc.invalidateQueries({ queryKey: ["admin", "users"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  function confirmToggle(u: AdminUserRow) {
    if (u.active && !window.confirm(`Deactivate ${u.name}? They will be signed out and unable to log in.`)) return;
    toggle.mutate(u);
  }

  const rows = data?.data ?? [];
  const meta = data?.meta;

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">User Management</h1>
        <p className="text-muted-foreground text-sm mt-1">
          Search platform accounts and deactivate ones that should no longer have access
        </p>
      </div>

      <div className="flex flex-wrap gap-2 mb-4">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input
            className="pl-9"
            placeholder="Search name or email"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            aria-label="Search users"
          />
        </div>
        <select className={SELECT_CLASS} value={role} aria-label="Role" onChange={(e) => { setRole(e.target.value); setPage(1); }}>
          {ROLES.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
        </select>
        <select className={SELECT_CLASS} value={status} aria-label="Status" onChange={(e) => { setStatus(e.target.value); setPage(1); }}>
          {STATUSES.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
        </select>
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-12 rounded-lg" />)}</div>
      ) : error ? (
        <div className="py-12 text-center text-sm text-destructive">{(error as Error).message}</div>
      ) : !rows.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <Users className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No users match these filters.
        </div>
      ) : (
        <div className="rounded-xl border bg-card overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b bg-muted/40">
                <tr>
                  {["Name", "Email", "Role", "School", "Joined", "Status", ""].map((h) => (
                    <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y">
                {rows.map((user) => (
                  <tr key={user.id} className="hover:bg-muted/20 transition-colors" data-testid={`row-user-${user.id}`}>
                    <td className="px-4 py-3 font-medium">{user.name}</td>
                    <td className="px-4 py-3 text-muted-foreground">{user.email}</td>
                    <td className="px-4 py-3">
                      <Badge
                        variant={user.role === "admin" ? "default" : user.role === "school" ? "secondary" : "outline"}
                        className="text-xs capitalize"
                      >
                        {user.role}
                      </Badge>
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">{user.schoolName ?? "—"}</td>
                    <td className="px-4 py-3 text-muted-foreground">
                      {user.createdAt ? new Date(user.createdAt).toLocaleDateString() : "—"}
                    </td>
                    <td className="px-4 py-3">
                      {user.active ? (
                        <span className="text-xs text-green-700 dark:text-green-400">Active</span>
                      ) : (
                        <span className="text-xs text-destructive">Deactivated</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Button
                        size="sm"
                        variant={user.active ? "outline" : "secondary"}
                        disabled={toggle.isPending}
                        onClick={() => confirmToggle(user)}
                      >
                        {user.active ? "Deactivate" : "Reactivate"}
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {meta && meta.lastPage > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm text-muted-foreground">
          <span>{meta.total} users · page {meta.page} of {meta.lastPage}</span>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Previous</Button>
            <Button size="sm" variant="outline" disabled={page >= meta.lastPage} onClick={() => setPage((p) => p + 1)}>Next</Button>
          </div>
        </div>
      )}
    </AdminLayout>
  );
}
