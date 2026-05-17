import { useState } from "react";
import { useLocation, Link } from "wouter";
import { motion } from "framer-motion";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAuthStore } from "@/lib/store";
import { Car, ShieldCheck, Star } from "lucide-react";

export default function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const { login, authError, isAuthLoading, userRole } = useAuthStore();
  const [, setLocation] = useLocation();

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await login({ email, password });
      const role = useAuthStore.getState().userRole ?? userRole;
      if (role === "admin") setLocation("/admin");
      else if (role === "school") setLocation("/dashboard");
      else setLocation("/search");
    } catch {
      // state already contains error
    }
  };

  return (
    <div className="min-h-screen grid grid-cols-1 lg:grid-cols-2">
      <div className="hidden lg:flex flex-col bg-gradient-to-br from-[hsl(221,83%,12%)] via-[hsl(221,83%,20%)] to-[hsl(258,60%,25%)] text-white p-12 relative overflow-hidden">
        <div className="absolute inset-0 opacity-10" style={{ backgroundImage: "radial-gradient(circle at 30% 50%, rgba(255,255,255,0.2) 0%, transparent 60%)" }} />
        <Link href="/" className="font-bold text-2xl tracking-tight z-10">DriveIQ</Link>
        <div className="flex-1 flex flex-col justify-center z-10">
          <motion.div initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.6 }}>
            <h2 className="text-4xl font-bold mb-4 leading-tight">Welcome back to<br />DriveIQ Partner</h2>
            <p className="text-white/70 text-lg leading-relaxed max-w-sm">Manage your driving school, track leads, and grow your student base — all in one place.</p>
          </motion.div>
          <motion.div className="mt-12 space-y-4" initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.3 }}>
            {[{ icon: ShieldCheck, label: "Verified school listings" }, { icon: Star, label: "Manage student reviews" }, { icon: Car, label: "Real-time lead management" }].map((item) => (
              <div key={item.label} className="flex items-center gap-3 text-white/80">
                <div className="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center"><item.icon className="h-4 w-4" /></div>
                <span className="text-sm">{item.label}</span>
              </div>
            ))}
          </motion.div>
        </div>
        <div className="text-white/40 text-xs z-10">&copy; {new Date().getFullYear()} DriveIQ. All rights reserved.</div>
      </div>

      <div className="flex items-center justify-center p-8 bg-background">
        <motion.div className="w-full max-w-sm" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
          <div className="mb-8">
            <Link href="/" className="font-bold text-xl text-primary lg:hidden">DriveIQ</Link>
            <h1 className="text-2xl font-bold mt-4">Sign in to your account</h1>
            <p className="text-muted-foreground text-sm mt-1">Don't have an account? <Link href="/auth/register" className="text-primary hover:underline">Sign up</Link></p>
          </div>

          <form onSubmit={handleLogin} className="space-y-4">
            <div>
              <Label>Email</Label>
              <Input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="you@example.com" required data-testid="input-login-email" />
            </div>
            <div>
              <div className="flex items-center justify-between mb-1">
                <Label>Password</Label>
              </div>
              <Input type="password" value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••" required data-testid="input-login-password" />
            </div>

            {authError ? <p className="text-sm text-destructive">{authError}</p> : null}

            <Button type="submit" className="w-full" size="lg" data-testid="button-login-submit" disabled={isAuthLoading}>
              {isAuthLoading ? "Signing In..." : "Sign In"}
            </Button>
          </form>
        </motion.div>
      </div>
    </div>
  );
}
