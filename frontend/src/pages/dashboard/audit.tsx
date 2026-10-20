import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { AuditLogTable } from "@/components/audit/audit-log-table";
import { useSchoolId } from "@/hooks/use-school-id";
import { useAuthStore } from "@/lib/store";

/** Owner-only record of who changed what in the school (DIQ-912). */
export default function SchoolAuditPage() {
  const schoolId = useSchoolId();
  const isOwner = useAuthStore((s) => s.schoolRole) !== "manager";

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Activity log</h1>
        <p className="text-sm text-muted-foreground mt-1">Every change made by you, your managers and the platform. Entries cannot be edited or deleted.</p>
      </div>
      {!isOwner ? (
        <p className="text-sm text-muted-foreground">Only the school owner can view the activity log.</p>
      ) : schoolId ? (
        <AuditLogTable scope={{ schoolId }} />
      ) : null}
    </DashboardLayout>
  );
}
