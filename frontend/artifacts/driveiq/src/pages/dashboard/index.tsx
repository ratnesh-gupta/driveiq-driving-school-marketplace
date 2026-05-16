import { motion } from "framer-motion";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { useGetSchoolStats, useListInquiries } from "@workspace/api-client-react";
import { TrendingUp, Users, Star, MessageCircle, AlertCircle, CheckCircle2 } from "lucide-react";

const DEMO_SCHOOL_ID = 1;

function StatCard({ icon: Icon, label, value, sub, color }: { icon: React.ElementType; label: string; value: string | number; sub?: string; color: string }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      className="rounded-xl border bg-card p-5 flex gap-4 items-center"
    >
      <div className={`w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ${color}`}>
        <Icon className="h-6 w-6" />
      </div>
      <div>
        <div className="text-2xl font-bold">{value}</div>
        <div className="text-sm font-medium">{label}</div>
        {sub && <div className="text-xs text-muted-foreground mt-0.5">{sub}</div>}
      </div>
    </motion.div>
  );
}

export default function DashboardHomePage() {
  const { data: stats, isLoading } = useGetSchoolStats(DEMO_SCHOOL_ID);
  const { data: inquiries } = useListInquiries({ schoolId: DEMO_SCHOOL_ID });

  const recentInquiries = (inquiries || []).slice(0, 5);

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Dashboard Overview</h1>
        <p className="text-muted-foreground text-sm mt-1">Welcome back. Here's how your school is performing.</p>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          {Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
          <StatCard icon={Users} label="Total Inquiries" value={stats?.totalInquiries ?? 0} sub="All time" color="bg-blue-500/10 text-blue-600 dark:text-blue-400" />
          <StatCard icon={AlertCircle} label="Pending Leads" value={stats?.pendingInquiries ?? 0} sub="Awaiting response" color="bg-yellow-500/10 text-yellow-600 dark:text-yellow-400" />
          <StatCard icon={Star} label="Avg. Rating" value={stats?.avgRating?.toFixed(1) ?? "—"} sub={`${stats?.totalReviews ?? 0} reviews`} color="bg-purple-500/10 text-purple-600 dark:text-purple-400" />
          <StatCard icon={TrendingUp} label="This Month" value={stats?.thisMonthInquiries ?? 0} sub="New inquiries" color="bg-green-500/10 text-green-600 dark:text-green-400" />
        </div>
      )}

      <div className="rounded-xl border bg-card">
        <div className="p-5 border-b flex items-center justify-between">
          <h2 className="font-semibold">Recent Inquiries</h2>
          <a href="/dashboard/leads" className="text-sm text-primary hover:underline">View all</a>
        </div>
        {recentInquiries.length === 0 ? (
          <div className="py-12 text-center text-muted-foreground text-sm">
            <MessageCircle className="h-8 w-8 mx-auto mb-2 opacity-30" />
            No inquiries yet.
          </div>
        ) : (
          <div className="divide-y">
            {recentInquiries.map((inq) => (
              <div key={inq.id} className="flex items-center justify-between px-5 py-3.5" data-testid={`row-inquiry-${inq.id}`}>
                <div>
                  <div className="font-medium text-sm">{inq.name}</div>
                  <div className="text-xs text-muted-foreground">{inq.vehicleType} • {inq.phone}</div>
                </div>
                <div className="flex items-center gap-2">
                  <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                    inq.status === "pending" ? "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400" :
                    inq.status === "contacted" ? "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400" :
                    inq.status === "enrolled" ? "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400" :
                    "bg-muted text-muted-foreground"
                  }`}>{inq.status}</span>
                  <span className="text-xs text-muted-foreground">{new Date(inq.createdAt).toLocaleDateString()}</span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}
