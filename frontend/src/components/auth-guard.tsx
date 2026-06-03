import { useEffect } from "react";
import { useLocation } from "wouter";
import { useAuthStore } from "@/lib/store";
import { Loader2 } from "lucide-react";

export function AuthGuard({ children, requireRole }: { children: React.ReactNode, requireRole?: "school" | "admin" }) {
  const { isLoggedIn, userRole, isAuthLoading } = useAuthStore();
  const [, setLocation] = useLocation();

  const hasAccess = !requireRole
    || userRole === requireRole
    || (requireRole === "school" && userRole === "admin");

  useEffect(() => {
    if (isAuthLoading) return;

    if (!isLoggedIn) {
      setLocation("/auth/login");
    } else if (!hasAccess) {
      setLocation("/");
    }
  }, [isLoggedIn, hasAccess, setLocation, isAuthLoading]);

  if (isAuthLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  if (!isLoggedIn || !hasAccess) {
    return null;
  }

  return <>{children}</>;
}
