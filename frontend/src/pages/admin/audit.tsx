import { AdminLayout } from "@/components/layout/admin-layout";
import { AuditLogTable } from "@/components/audit/audit-log-table";

/** Platform-wide audit trail (DIQ-912). */
export default function AdminAuditPage() {
  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Audit log</h1>
        <p className="text-sm text-muted-foreground mt-1">Append-only record of changes across all schools.</p>
      </div>
      <AuditLogTable scope="admin" />
    </AdminLayout>
  );
}
