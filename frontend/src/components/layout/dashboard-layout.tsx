import { Link, useLocation } from "wouter";
import { useAuthStore } from "@/lib/store";
import { Button } from "@/components/ui/button";
import { LayoutDashboard, Users, User, Package as PkgIcon, Star, BarChart3, LogOut, Menu } from "lucide-react";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";

const DASHBOARD_LINKS = [
  { href: "/dashboard", label: "Overview", icon: LayoutDashboard },
  { href: "/dashboard/leads", label: "Leads", icon: Users },
  { href: "/dashboard/profile", label: "Profile", icon: User },
  { href: "/dashboard/packages", label: "Packages", icon: PkgIcon },
  { href: "/dashboard/reviews", label: "Reviews", icon: Star },
  { href: "/dashboard/analytics", label: "Analytics", icon: BarChart3 },
];

export function DashboardLayout({ children }: { children: React.ReactNode }) {
  const [location] = useLocation();
  const { logout } = useAuthStore();

  const SidebarContent = () => (
    <div className="flex flex-col h-full">
      <div className="h-16 flex items-center px-6 border-b">
        <Link href="/" className="font-bold text-xl text-primary tracking-tight">DriveIQ Partner</Link>
      </div>
      <div className="flex-1 py-6 flex flex-col gap-2 px-4">
        {DASHBOARD_LINKS.map((link) => {
          const Icon = link.icon;
          const isActive = location === link.href;
          return (
            <Link key={link.href} href={link.href}>
              <Button
                variant={isActive ? "secondary" : "ghost"}
                className={`w-full justify-start ${isActive ? "bg-secondary text-primary" : "text-muted-foreground"}`}
              >
                <Icon className="mr-2 h-4 w-4" />
                {link.label}
              </Button>
            </Link>
          );
        })}
      </div>
      <div className="p-4 border-t">
        <Button variant="ghost" className="w-full justify-start text-destructive hover:text-destructive hover:bg-destructive/10" onClick={() => { void logout(); }}>
          <LogOut className="mr-2 h-4 w-4" />
          Logout
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
              <SheetTrigger asChild>
                <Button variant="ghost" size="icon">
                  <Menu className="h-5 w-5" />
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="p-0 w-64">
                <SidebarContent />
              </SheetContent>
            </Sheet>
          </div>
          <div className="font-semibold text-lg ml-2 md:ml-0">Dashboard</div>
          <div className="flex items-center gap-4">
            {/* Notifications or User menu could go here */}
          </div>
        </header>
        <main className="flex-1 overflow-auto p-4 md:p-8">
          {children}
        </main>
      </div>
    </div>
  );
}
