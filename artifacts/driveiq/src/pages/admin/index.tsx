import { motion } from "framer-motion";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { useGetStatsOverview } from "@workspace/api-client-react";
import { Building2, MapPin, Star, Users, ShieldCheck, BarChart3 } from "lucide-react";

function StatCard({ icon: Icon, label, value, color }: { icon: React.ElementType; label: string; value: number | string; color: string }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      className="rounded-xl border bg-card p-5 flex items-center gap-4"
    >
      <div className={`w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 ${color}`}>
        <Icon className="h-6 w-6" />
      </div>
      <div>
        <div className="text-2xl font-bold">{value}</div>
        <div className="text-sm text-muted-foreground">{label}</div>
      </div>
    </motion.div>
  );
}

export default function AdminHomePage() {
  const { data: stats, isLoading } = useGetStatsOverview();

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Platform Overview</h1>
        <p className="text-muted-foreground text-sm mt-1">DriveIQ platform statistics</p>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <StatCard icon={Building2} label="Total Schools" value={stats?.totalSchools ?? 0} color="bg-blue-500/10 text-blue-600 dark:text-blue-400" />
          <StatCard icon={ShieldCheck} label="Verified Schools" value={stats?.verifiedSchools ?? 0} color="bg-green-500/10 text-green-600 dark:text-green-400" />
          <StatCard icon={MapPin} label="Localities" value={stats?.totalLocalities ?? 0} color="bg-purple-500/10 text-purple-600 dark:text-purple-400" />
          <StatCard icon={Star} label="Total Reviews" value={stats?.totalReviews ?? 0} color="bg-yellow-500/10 text-yellow-600 dark:text-yellow-400" />
          <StatCard icon={Users} label="Total Inquiries" value={stats?.totalInquiries ?? 0} color="bg-orange-500/10 text-orange-600 dark:text-orange-400" />
          <StatCard icon={BarChart3} label="Avg Rating" value={stats?.avgRating?.toFixed(1) ?? "—"} color="bg-pink-500/10 text-pink-600 dark:text-pink-400" />
        </div>
      )}
    </AdminLayout>
  );
}
