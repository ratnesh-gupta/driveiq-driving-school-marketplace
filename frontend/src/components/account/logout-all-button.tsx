import { ShieldOff } from "lucide-react";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { useConfirm } from "@/components/confirm-dialog";
import { useAuthStore } from "@/lib/store";
import { useT } from "@/i18n/use-locale";

/** "Sign out of all devices": revokes every token, then clears this session like a normal logout. */
export function LogoutAllButton({ className }: { className?: string }) {
  const t = useT();
  const { confirm } = useConfirm();
  const logoutAll = useAuthStore((s) => s.logoutAll);

  const onClick = async () => {
    const ok = await confirm({
      title: t("account.title"),
      description: t("account.body"),
      confirmLabel: t("account.confirm"),
      destructive: true,
      testId: "dialog-logout-all",
    });
    if (!ok) return;
    try {
      await logoutAll();
    } catch {
      toast.error(t("account.failed"));
    }
  };

  return (
    <Button
      variant="ghost"
      className={className ?? "w-full justify-start text-muted-foreground"}
      onClick={() => { void onClick(); }}
      data-testid="button-logout-all"
    >
      <ShieldOff className="mr-2 h-4 w-4" />
      {t("account.logoutAll")}
    </Button>
  );
}
