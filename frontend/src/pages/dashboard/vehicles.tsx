import { useQuery } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Skeleton } from "@/components/ui/skeleton";
import { useSchoolId } from "@/hooks/use-school-id";
import { listVehicles } from "@/lib/ops-api";
import { Car } from "lucide-react";

type VehicleRow = {
  id: number;
  registrationNumber: string;
  type: string;
  transmission?: string;
  status: string;
  makeModel?: string;
};

export default function VehiclesPage() {
  const schoolId = useSchoolId();
  const { data, isLoading } = useQuery({
    queryKey: ["vehicles", schoolId],
    queryFn: () => listVehicles(schoolId!) as Promise<VehicleRow[]>,
    enabled: !!schoolId,
  });

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Vehicles</h1>
        <p className="text-sm text-muted-foreground mt-1">Fleet status and availability</p>
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <Car className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No vehicles registered.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((v) => (
            <div key={v.id} className="flex items-center justify-between px-5 py-3.5">
              <div>
                <div className="font-medium text-sm">{v.registrationNumber}</div>
                <div className="text-xs text-muted-foreground">
                  {[v.type, v.transmission, v.makeModel].filter(Boolean).join(" · ")}
                </div>
              </div>
              <span className="text-xs px-2 py-0.5 rounded-full bg-muted font-medium">{v.status}</span>
            </div>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}
