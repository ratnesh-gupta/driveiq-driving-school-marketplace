import { Link, useLocation } from "wouter";
import { useAuthStore } from "@/lib/store";
import { Button } from "@/components/ui/button";
import { LayoutDashboard, CalendarDays, MessageSquare, LogOut, Menu, TrendingUp, FileText } from "lucide-react";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { NotificationBell } from "@/components/notifications/notification-bell";
import { LanguageSwitcher } from "@/components/language-switcher";
import { useT } from "@/i18n/use-locale";

export function LearnerLayout({ children }: { children: React.ReactNode }) {
  const [location] = useLocation();
  const { logout, user } = useAuthStore();
  const t = useT();

  const links = [
    { href: "/learner", label: t("learnerNav.overview"), icon: LayoutDashboard },
    { href: "/learner/progress", label: t("learnerNav.progress"), icon: TrendingUp },
    { href: "/learner/sessions", label: t("learnerNav.sessions"), icon: CalendarDays },
    { href: "/learner/documents", label: t("learnerNav.documents"), icon: FileText },
    { href: "/learner/messages", label: t("learnerNav.messages"), icon: MessageSquare },
  ];

  const SidebarContent = () => (
    <div className="flex flex-col h-full">
      <div className="h-16 flex items-center px-6 border-b">
        <Link href="/" className="font-bold text-xl text-primary tracking-tight">{t("learnerNav.brand")}</Link>
      </div>
      <div className="px-4 py-3 text-xs text-muted-foreground border-b">{user?.name}</div>
      <div className="flex-1 py-6 flex flex-col gap-1 px-3">
        {links.map((link) => {
          const Icon = link.icon;
          const isActive = location === link.href;
          return (
            <Link key={link.href} href={link.href}>
              <Button variant={isActive ? "secondary" : "ghost"} className={`w-full justify-start ${isActive ? "bg-secondary text-primary" : "text-muted-foreground"`}>
                <Icon className="mr-2 h-4 w-4" />
                {link.label}
              </Button>
            </Link>
          );
        })}
      </div>
      <div className="p-4 border-t">
        <Button variant="ghost" className="w-full justify-start text-destructive" onClick={() => { void logout(); }}>
          <LogOut className="mr-2 h-4 w-4" /> {t("common.logout")}
        </Button>
      </div>
    </div>
  );

  return (
    <div className="min-h-[100dvh] flex flex-col md:flex-row bg-muted/20">
      <aside className="hidden md:flex w-64 flex-col border-r bg-card min-h-[100dvh]">
        <SidebarContent />
      </aside>
      <div className="flex-1 flex flex-col min-h-0 overflow-hidden">
        <header className="h-16 border-b bg-card flex items-center justify-between px-4 md:px-8">
          <div className="flex items-center md:hidden">
            <Sheet>
              <SheetTrigger asChild><Button variant="ghost" size="icon"><Menu className="h-5 w-5" /></Button></SheetTrigger>
              <SheetContent side="left" className="p-0 w-64"><SidebarContent /></SheetContent>
            </Sheet>
          </div>
          <div className="font-semibold text-lg ml-2 md:ml-0">{t("learnerNav.title")}</div>
          <div className="flex items-center gap-2">
            <LanguageSwitcher />
            <NotificationBell />
          </div>
        </header>
        <main className="flex-1 overflow-auto p-4 md:p-8">{children}</main>
      </div>
    </div>
  );
}
