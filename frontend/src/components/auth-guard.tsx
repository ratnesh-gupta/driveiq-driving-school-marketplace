import { useEffect } from "react";
import { useLocation } from "wouter";
import { useAuthStore } from "@/lib/store";
import { roleHomePath, type UserRole } from "@/lib/auth-api";
import { Loader2 } from "lucide-react";

type GuardRole = UserRole | UserRole[];

export function AuthGuard({
  children,
  requireRole,
}: {
  children: React.ReactNode;
  requireRole?: GuardRole;
}) {
  const { isLoggedIn, userRole, isAuthLoading } = useAuthStore();
  const [, setLocation] = useLocation();

  const allowed = Array.isArray(requireRole)
    ? requireRole
    : requireRole
      ? [requireRole]
      : null;

  const hasAccess =
    !allowed ||
    (userRole != null &&
      (allowed.includes(userRole) ||
        (allowed.includes("school") && userRole === "admin")));

  useEffect(() => {
    if (isAuthLoading) return;

    if (!isLoggedIn) {
      setLocation("/auth/login");
    } else if (!hasAccess) {
      setLocation(roleHomePath(userRole));
    }
  }, [isLoggedIn, hasAccess, setLocation, isAuthLoading, userRole]);

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
