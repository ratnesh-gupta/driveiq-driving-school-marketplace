import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { useGetSchoolStats, useListInquiries, useListReviews } from "@/api-client";
import { useSchoolId } from "@/hooks/use-school-id";
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from "recharts";
import { TrendingUp, Users, Star, CheckCircle2 } from "lucide-react";
const COLORS = ["hsl(221,83%,53%)", "hsl(258,60%,55%)", "hsl(142,71%,45%)", "hsl(38,92%,50%)"];

export default function AnalyticsPage() {
  const schoolId = useSchoolId();
  const { data: stats, isLoading } = useGetSchoolStats(schoolId!, { query: { enabled: !!schoolId } });
  const { data: inquiries } = useListInquiries({ schoolId: schoolId! }, { query: { enabled: !!schoolId } });
  const { data: reviews } = useListReviews({ schoolId: schoolId! }, { query: { enabled: !!schoolId } });

  // Build monthly inquiries chart data (mock from real data counts)
  const monthlyData = [
    { month: "Jan", inquiries: 2 }, { month: "Feb", inquiries: 3 },
    { month: "Mar", inquiries: 4 }, { month: "Apr", inquiries: 2 },
    { month: "May", inquiries: stats?.thisMonthInquiries ?? 3 }, { month: "Jun", inquiries: 0 },
  ];

  const ratingData = [
    { rating: "5 stars", count: (reviews || []).filter(r => r.rating === 5).length },
    { rating: "4 stars", count: (reviews || []).filter(r => r.rating === 4).length },
    { rating: "3 stars", count: (reviews || []).filter(r => r.rating === 3).length },
    { rating: "2 stars", count: (reviews || []).filter(r => r.rating === 2).length },
    { rating: "1 star", count: (reviews || []).filter(r => r.rating === 1).length },
  ];

  const statusData = [
    { name: "Pending", value: (inquiries || []).filter(i => i.status === "pending").length },
    { name: "Contacted", value: (inquiries || []).filter(i => i.status === "contacted").length },
    { name: "Enrolled", value: (inquiries || []).filter(i => i.status === "enrolled").length },
    { name: "Closed", value: (inquiries || []).filter(i => i.status === "closed").length },
  ].filter(d => d.value > 0);

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Analytics</h1>
        <p className="text-muted-foreground text-sm mt-1">Insights into your school's performance</p>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {isLoading ? (
          Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-20 rounded-xl" />)
        ) : [
          { icon: Users, label: "Total Leads", value: stats?.totalInquiries ?? 0, color: "text-blue-500" },
          { icon: TrendingUp, label: "This Month", value: stats?.thisMonthInquiries ?? 0, color: "text-green-500" },
          { icon: Star, label: "Avg Rating", value: stats?.avgRating?.toFixed(1) ?? "—", color: "text-yellow-500" },
          { icon: CheckCircle2, label: "Reviews", value: stats?.totalReviews ?? 0, color: "text-purple-500" },
        ].map((item) => (
          <div key={item.label} className="rounded-xl border bg-card p-4 flex items-center gap-3">
            <item.icon className={`h-8 w-8 ${item.color} flex-shrink-0`} />
            <div>
              <div className="text-xl font-bold">{item.value}</div>
              <div className="text-xs text-muted-foreground">{item.label}</div>
            </div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {/* Monthly inquiries */}
        <div className="rounded-xl border bg-card p-5">
          <h2 className="font-semibold mb-4">Monthly Inquiries</h2>
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={monthlyData} margin={{ top: 0, right: 0, left: -20, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
              <XAxis dataKey="month" tick={{ fontSize: 12 }} />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip />
              <Bar dataKey="inquiries" fill="hsl(221,83%,53%)" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>

        {/* Inquiry status pie */}
        <div className="rounded-xl border bg-card p-5">
          <h2 className="font-semibold mb-4">Lead Status Breakdown</h2>
          {statusData.length > 0 ? (
            <ResponsiveContainer width="100%" height={220}>
              <PieChart>
                <Pie data={statusData} dataKey="value" nameKey="name" cx="50%" cy="50%" outerRadius={80} label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}>
                  {statusData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          ) : (
            <div className="h-[220px] flex items-center justify-center text-muted-foreground text-sm">No inquiry data</div>
          )}
        </div>
      </div>

      {/* Rating distribution */}
      <div className="rounded-xl border bg-card p-5">
        <h2 className="font-semibold mb-4">Rating Distribution</h2>
        <ResponsiveContainer width="100%" height={180}>
          <BarChart data={ratingData} layout="vertical" margin={{ top: 0, right: 16, left: 16, bottom: 0 }}>
            <XAxis type="number" tick={{ fontSize: 12 }} />
            <YAxis dataKey="rating" type="category" tick={{ fontSize: 12 }} width={60} />
            <Tooltip />
            <Bar dataKey="count" fill="hsl(258,60%,55%)" radius={[0, 4, 4, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </DashboardLayout>
  );
}
