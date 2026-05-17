import { useEffect } from "react";
import { useLocation } from "wouter";
import { useAuthStore } from "@/lib/store";

export function AuthGuard({ children, requireRole }: { children: React.ReactNode, requireRole?: "school" | "admin" }) {
  const { isLoggedIn, userRole, isAuthLoading } = useAuthStore();
  const [, setLocation] = useLocation();

  useEffect(() => {
    if (isAuthLoading) return;

    if (!isLoggedIn) {
      setLocation("/auth/login");
    } else if (requireRole && userRole !== requireRole) {
      setLocation("/");
    }
  }, [isLoggedIn, userRole, requireRole, setLocation, isAuthLoading]);

  if (isAuthLoading) return null;
  if (!isLoggedIn || (requireRole && userRole !== requireRole)) {
    return null;
  }

  return <>{children}</>;
}
