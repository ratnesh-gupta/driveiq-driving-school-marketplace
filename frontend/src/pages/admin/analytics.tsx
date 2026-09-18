import { useQuery } from "@tanstack/react-query";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchPlatformAnalytics } from "@/lib/ops-api";
import { BarChart3 } from "lucide-react";

export default function AdminAnalyticsPage() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ["admin", "analytics"],
    queryFn: fetchPlatformAnalytics,
  });

  const schools = data?.schools as { total?: number; verified?: number; subscribed?: number } | undefined;
  const funnel = data?.funnel as { inquiries?: number; converted?: number; conversionRate?: number } | undefined;
  const revenue = data?.revenue as { mrr?: number; arr?: number } | undefined;

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Platform analytics</h1>
        <p className="text-sm text-muted-foreground mt-1">Business metrics across DriveIQ</p>
      </div>

      {isLoading ? (
        <div className="grid sm:grid-cols-3 gap-4">{Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)}</div>
      ) : isError ? (
        <div className="py-12 text-center text-muted-foreground">
          <BarChart3 className="h-10 w-10 mx-auto mb-2 opacity-30" />
          Unable to load analytics
        </div>
      ) : (
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <Metric label="Schools" value={schools?.total ?? 0} sub={`${schools?.verified ?? 0} verified`} />
          <Metric label="Subscribed" value={schools?.subscribed ?? 0} />
          <Metric label="Inquiries" value={funnel?.inquiries ?? 0} />
          <Metric label="Converted" value={funnel?.converted ?? 0} sub={`${((funnel?.conversionRate ?? 0) * 100).toFixed(1)}% rate`} />
          <Metric label="MRR" value={`₹${revenue?.mrr ?? 0}`} />
          <Metric label="ARR" value={`₹${revenue?.arr ?? 0}`} />
        </div>
      )}
    </AdminLayout>
  );
}

function Metric({ label, value, sub }: { label: string; value: string | number; sub?: string }) {
  return (
    <div className="rounded-xl border bg-card p-5">
      <div className="text-2xl font-bold">{value}</div>
      <div className="text-sm font-medium">{label}</div>
      {sub && <div className="text-xs text-muted-foreground mt-1">{sub}</div>}
    </div>
  );
}
