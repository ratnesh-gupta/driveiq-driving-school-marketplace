import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/hooks/use-toast";
import { useSchoolId } from "@/hooks/use-school-id";
import { useAuthStore } from "@/lib/store";
import { inviteManager, listTeam, removeTeamMember } from "@/lib/ops-api";
import { useT } from "@/i18n/use-locale";
import { UserPlus, Users } from "lucide-react";

export default function TeamPage() {
  const t = useT();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const schoolId = useSchoolId();
  const isOwner = useAuthStore((s) => s.schoolRole === "owner");
  const [email, setEmail] = useState("");

  const teamKey = ["team", schoolId];
  const { data: members, isLoading } = useQuery({
    queryKey: teamKey,
    queryFn: () => listTeam(schoolId!),
    enabled: !!schoolId,
  });

  const invite = useMutation({
    mutationFn: () => inviteManager(schoolId!, email),
    onSuccess: () => {
      setEmail("");
      toast({ title: t("team.invited") });
      queryClient.invalidateQueries({ queryKey: teamKey });
    },
    onError: (e: Error) => toast({ title: e.message, variant: "destructive" }),
  });

  const remove = useMutation({
    mutationFn: (memberId: number) => removeTeamMember(schoolId!, memberId),
    onSuccess: () => {
      toast({ title: t("team.removed") });
      queryClient.invalidateQueries({ queryKey: teamKey });
    },
    onError: (e: Error) => toast({ title: e.message, variant: "destructive" }),
  });

  const managers = members?.filter((m) => m.role === "manager") ?? [];

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">{t("team.title")}</h1>
        <p className="text-muted-foreground text-sm mt-1">{t("team.subtitle")}</p>
      </div>

      {isOwner && (
        <form
          onSubmit={(e) => { e.preventDefault(); invite.mutate(); }}
          className="rounded-xl border bg-card p-5 mb-6 space-y-3"
        >
          <h2 className="font-semibold flex items-center gap-2"><UserPlus className="h-4 w-4" /> {t("team.inviteTitle")}</h2>
          <p className="text-xs text-muted-foreground">{t("team.inviteHelp")}</p>
          <div className="flex gap-2">
            <Input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder={t("team.email")} aria-label={t("team.email")} data-testid="input-invite-email" />
            <Button type="submit" disabled={invite.isPending} data-testid="button-invite-submit">
              {invite.isPending ? t("team.sending") : t("team.sendInvite")}
            </Button>
          </div>
        </form>
      )}

      <div className="rounded-xl border bg-card divide-y">
        {isLoading ? (
          <div className="p-5"><Skeleton className="h-12" /></div>
        ) : (
          <>
            {members?.map((m) => (
              <div key={m.id} className="flex items-center justify-between gap-4 p-4" data-testid={`row-team-${m.id}`}>
                <div className="min-w-0">
                  <div className="font-medium text-sm truncate">{m.name ?? m.email}</div>
                  {m.name && <div className="text-xs text-muted-foreground truncate">{m.email}</div>}
                </div>
                <div className="flex items-center gap-2 flex-shrink-0">
                  <Badge variant={m.role === "owner" ? "default" : "secondary"}>{t(`team.${m.role}`)}</Badge>
                  {m.status === "pending" && <Badge variant="outline">{t("team.pending")}</Badge>}
                  {isOwner && m.role !== "owner" && (
                    <Button size="sm" variant="ghost" className="text-destructive" disabled={remove.isPending} onClick={() => remove.mutate(m.id)} data-testid={`button-remove-team-${m.id}`}>
                      {m.status === "pending" ? t("team.revokeInvite") : t("team.remove")}
                    </Button>
                  )}
                </div>
              </div>
            ))}
            {managers.length === 0 && (
              <div className="py-10 text-center text-sm text-muted-foreground">
                <Users className="h-8 w-8 mx-auto mb-2 opacity-30" />
                {t("team.empty")}
              </div>
            )}
          </>
        )}
      </div>
    </DashboardLayout>
  );
}
