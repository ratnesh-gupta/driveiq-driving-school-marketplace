import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/hooks/use-toast";
import { useListSchools, useUpdateSchool, useDeleteSchool, getListSchoolsQueryKey } from "@/api-client";
import { useQueryClient } from "@tanstack/react-query";
import { ShieldCheck, Trash2, Building2, Star } from "lucide-react";

export default function AdminSchoolsPage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const { data: schools, isLoading } = useListSchools();
  const updateSchool = useUpdateSchool();
  const deleteSchool = useDeleteSchool();

  const toggleVerify = (id: number, verified: boolean) => {
    updateSchool.mutate({ id, data: { verified: !verified } }, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListSchoolsQueryKey() });
        toast({ title: verified ? "School unverified" : "School verified" });
      },
    });
  };

  const remove = (id: number) => {
    deleteSchool.mutate({ id }, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListSchoolsQueryKey() });
        toast({ title: "School removed" });
      },
    });
  };

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">School Moderation</h1>
        <p className="text-muted-foreground text-sm mt-1">Verify and manage all listed schools</p>
      </div>

      <div className="rounded-xl border bg-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b bg-muted/40">
              <tr>
                {["School", "Locality", "Rating", "Status", "Actions"].map(h => (
                  <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y">
              {isLoading ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i}><td colSpan={5} className="px-4 py-3"><Skeleton className="h-8" /></td></tr>
                ))
              ) : !schools?.length ? (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-muted-foreground">
                    <Building2 className="h-8 w-8 mx-auto mb-2 opacity-30" />
                    No schools found
                  </td>
                </tr>
              ) : schools.map((school) => (
                <tr key={school.id} className="hover:bg-muted/20 transition-colors" data-testid={`row-school-${school.id}`}>
                  <td className="px-4 py-3">
                    <div className="font-medium">{school.name}</div>
                    <div className="text-xs text-muted-foreground truncate max-w-48">{school.address}</div>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">{school.localityName || "—"}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-1">
                      <Star className="h-3.5 w-3.5 fill-yellow-400 text-yellow-400" />
                      <span>{school.rating.toFixed(1)}</span>
                      <span className="text-muted-foreground text-xs">({school.reviewCount})</span>
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    {school.verified ? (
                      <Badge className="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border-0 text-xs">
                        <ShieldCheck className="h-3 w-3 mr-1" /> Verified
                      </Badge>
                    ) : (
                      <Badge variant="outline" className="text-xs text-muted-foreground">Unverified</Badge>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" className="h-8 text-xs" onClick={() => toggleVerify(school.id, school.verified)} data-testid={`button-verify-school-${school.id}`}>
                        {school.verified ? "Unverify" : "Verify"}
                      </Button>
                      <Button size="sm" variant="ghost" className="h-8 w-8 p-0 text-destructive hover:text-destructive hover:bg-destructive/10" onClick={() => remove(school.id)} data-testid={`button-delete-school-${school.id}`}>
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </AdminLayout>
  );
}
