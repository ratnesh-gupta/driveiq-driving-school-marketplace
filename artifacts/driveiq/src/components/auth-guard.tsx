import { useEffect } from "react";
import { useLocation } from "wouter";
import { useAuthStore } from "@/lib/store";

export function AuthGuard({ children, requireRole }: { children: React.ReactNode, requireRole?: "school" | "admin" }) {
  const { isLoggedIn, userRole } = useAuthStore();
  const [, setLocation] = useLocation();

  useEffect(() => {
    if (!isLoggedIn) {
      setLocation("/auth/login");
    } else if (requireRole && userRole !== requireRole) {
      setLocation("/");
    }
  }, [isLoggedIn, userRole, requireRole, setLocation]);

  if (!isLoggedIn || (requireRole && userRole !== requireRole)) {
    return null;
  }

  return <>{children}</>;
}
