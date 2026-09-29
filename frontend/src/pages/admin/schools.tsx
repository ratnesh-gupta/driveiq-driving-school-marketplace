import { useState } from "react";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { useToast } from "@/hooks/use-toast";
import { useDeleteSchool } from "@/api-client";
import type { School } from "@/api-client/generated/api.schemas";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { listSchoolsPage, updateSchoolVerification, type VerificationFlags } from "@/lib/ops-api";
import { ShieldCheck, Trash2, Building2, Star, Phone, Briefcase, MapPin, Crown } from "lucide-react";

const PAGE_SIZE = 25;

const CHECKS: { key: keyof Omit<VerificationFlags, "verified">; label: string; help: string; icon: typeof Phone }[] = [
  { key: "phoneVerified", label: "Phone verified", help: "We reached the school on its listed number.", icon: Phone },
  { key: "businessVerified", label: "Business verified", help: "Registration / RTO licence documents checked.", icon: Briefcase },
  { key: "locationVerified", label: "Location verified", help: "Address and map pin match the premises.", icon: MapPin },
  { key: "premiumVerified", label: "Premium verified", help: "Premium-tier checks completed.", icon: Crown },
];

function flagsOf(school: School): VerificationFlags {
  return {
    verified: school.verified,
    phoneVerified: !!school.phoneVerified,
    businessVerified: !!school.businessVerified,
    locationVerified: !!school.locationVerified,
    premiumVerified: !!school.premiumVerified,
  };
}

/** Admin school moderation + verification workflow (DIQ-506). Every change is audited server-side. */
export default function AdminSchoolsPage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(0);
  const [reviewing, setReviewing] = useState<School | null>(null);
  const [flags, setFlags] = useState<VerificationFlags | null>(null);
  const [deleting, setDeleting] = useState<School | null>(null);

  const queryKey = ["admin", "schools", page];
  const { data, isLoading } = useQuery({
    queryKey,
    queryFn: () => listSchoolsPage<School>({ limit: PAGE_SIZE, offset: page * PAGE_SIZE }),
  });
  const schools = data?.schools ?? [];
  const total = data?.total ?? 0;
  const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  const save = useMutation({
    mutationFn: () => updateSchoolVerification(reviewing!.id, flags!),
    onSuccess: () => {
      toast({ title: "Verification updated", description: reviewing?.name });
      setReviewing(null);
      queryClient.invalidateQueries({ queryKey: ["admin", "schools"] });
    },
    onError: (e: Error) => toast({ title: "Could not update verification", description: e.message, variant: "destructive" }),
  });

  const deleteSchool = useDeleteSchool();
  const confirmDelete = () => {
    if (!deleting) return;
    deleteSchool.mutate({ id: deleting.id }, {
      onSuccess: () => {
        toast({ title: "School removed", description: deleting.name });
        setDeleting(null);
        queryClient.invalidateQueries({ queryKey: ["admin", "schools"] });
      },
    });
  };

  const openReview = (school: School) => {
    setReviewing(school);
    setFlags(flagsOf(school));
  };

  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">School Moderation</h1>
        <p className="text-muted-foreground text-sm mt-1">Verify and manage all listed schools. Every change is recorded in the audit log.</p>
      </div>

      <div className="rounded-xl border bg-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b bg-muted/40">
              <tr>
                {["School", "Locality", "Rating", "Verification", "Actions"].map(h => (
                  <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y">
              {isLoading ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i}><td colSpan={5} className="px-4 py-3"><Skeleton className="h-8" /></td></tr>
                ))
              ) : !schools.length ? (
                <tr>
                  <td colSpan={5} className="text-center py-12 text-muted-foreground">
                    <Building2 className="h-8 w-8 mx-auto mb-2 opacity-30" />
                    No schools found
                  </td>
                </tr>
              ) : schools.map((school) => {
                const f = flagsOf(school);
                const done = CHECKS.filter((c) => f[c.key]).length;
                return (
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
                      <div className="flex items-center gap-2">
                        {school.verified ? (
                          <Badge className="bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border-0 text-xs">
                            <ShieldCheck className="h-3 w-3 mr-1" /> Verified
                          </Badge>
                        ) : (
                          <Badge variant="outline" className="text-xs text-muted-foreground">Unverified</Badge>
                        )}
                        <span className="text-xs text-muted-foreground">{done}/{CHECKS.length} checks</span>
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex gap-2">
                        <Button size="sm" variant="outline" className="h-8 text-xs" onClick={() => openReview(school)} data-testid={`button-review-school-${school.id}`}>
                          Review
                        </Button>
                        <Button size="sm" variant="ghost" className="h-8 w-8 p-0 text-destructive hover:text-destructive hover:bg-destructive/10" aria-label={`Delete ${school.name}`} onClick={() => setDeleting(school)} data-testid={`button-delete-school-${school.id}`}>
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {total > PAGE_SIZE && (
          <div className="flex items-center justify-between border-t px-4 py-3 text-sm">
            <span className="text-muted-foreground">Page {page + 1} of {pages} · {total} schools</span>
            <div className="flex gap-2">
              <Button size="sm" variant="outline" disabled={page === 0} onClick={() => setPage((p) => p - 1)}>Previous</Button>
              <Button size="sm" variant="outline" disabled={page + 1 >= pages} onClick={() => setPage((p) => p + 1)}>Next</Button>
            </div>
          </div>
        )}
      </div>

      <Dialog open={!!reviewing} onOpenChange={(open) => !open && setReviewing(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Verify {reviewing?.name}</DialogTitle>
            <DialogDescription>Record which checks have been completed. The public "Verified" badge is set separately.</DialogDescription>
          </DialogHeader>
          {flags && (
            <div className="space-y-4">
              {CHECKS.map(({ key, label, help, icon: Icon }) => (
                <div key={key} className="flex items-start justify-between gap-4">
                  <div className="flex gap-3">
                    <Icon className="h-4 w-4 mt-0.5 text-muted-foreground" />
                    <div>
                      <Label htmlFor={`flag-${key}`}>{label}</Label>
                      <p className="text-xs text-muted-foreground">{help}</p>
                    </div>
                  </div>
                  <Switch id={`flag-${key}`} checked={flags[key]} onCheckedChange={(v) => setFlags({ ...flags, [key]: v })} />
                </div>
              ))}
              <div className="flex items-start justify-between gap-4 border-t pt-4">
                <div className="flex gap-3">
                  <ShieldCheck className="h-4 w-4 mt-0.5 text-green-600" />
                  <div>
                    <Label htmlFor="flag-verified">Show "Verified" badge</Label>
                    <p className="text-xs text-muted-foreground">Shown on search results, the school page and comparisons, and used in ranking.</p>
                  </div>
                </div>
                <Switch id="flag-verified" checked={flags.verified} onCheckedChange={(v) => setFlags({ ...flags, verified: v })} data-testid="switch-verified" />
              </div>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setReviewing(null)}>Cancel</Button>
            <Button onClick={() => save.mutate()} disabled={save.isPending} data-testid="button-save-verification">
              {save.isPending ? "Saving…" : "Save"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete {deleting?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              This removes the school and its listing permanently. Its audit history is kept.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90" data-testid="button-confirm-delete">
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AdminLayout>
  );
}
