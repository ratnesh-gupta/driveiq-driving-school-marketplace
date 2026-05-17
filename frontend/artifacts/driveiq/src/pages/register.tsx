import { useState } from "react";
import { useLocation, Link } from "wouter";
import { motion } from "framer-motion";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAuthStore } from "@/lib/store";
import { Users, Building2 } from "lucide-react";

type AccountType = "user" | "school";

export default function RegisterPage() {
  const [accountType, setAccountType] = useState<AccountType>("school");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const { register, isAuthLoading, authError, userRole } = useAuthStore();
  const [, setLocation] = useLocation();

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await register({ name, email, password, role: accountType });
      const role = useAuthStore.getState().userRole ?? userRole;
      setLocation(role === "school" ? "/dashboard" : "/search");
    } catch {
      // state already contains error
    }
  };

  return (
    <div className="min-h-screen grid grid-cols-1 lg:grid-cols-2">
  <div className="hidden lg:flex flex-col bg-gradient-to-br from-[hsl(258,60%,18%)] via-[hsl(221,83%,20%)] to-[hsl(221,83%,12%)] text-white p-12 relative overflow-hidden">
    <Link href="/" className="font-bold text-2xl tracking-tight z-10">DriveIQ</Link>
    <div className="flex-1 flex flex-col justify-center z-10 max-w-sm">
      <motion.div initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.6 }}>
        <h2 className="text-4xl font-bold mb-4">Join India's fastest growing driving school platform</h2>
            <p className="text-white/70 text-lg leading-relaxed">Whether you're a learner or a school owner, DriveIQ connects you to the best driving experiences in Pune.</p>
          </motion.div >
        </div >
    <div className="text-white/40 text-xs z-10">&copy; {new Date().getFullYear()} DriveIQ. All rights reserved.</div>
      </div >

    <div className="flex items-center justify-center p-8 bg-background">
      <motion.div className="w-full max-w-sm" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
        <div className="mb-8">
          <Link href="/" className="font-bold text-xl text-primary lg:hidden">DriveIQ</Link>
          <h1 className="text-2xl font-bold mt-4">Create your account</h1>
          <p className="text-muted-foreground text-sm mt-1">Already have an account? <Link href="/auth/login" className="text-primary hover:underline">Sign in</Link></p>
        </div>

  <div className="grid grid-cols-2 gap-3 mb-6">
    {([
      { type: "school" as const, icon: Building2, label: "Driving School", desc: "I manage a school" },
      { type: "user" as const, icon: Users, label: "Learner", desc: "I want to learn" },
    ]).map((item) => (
              <button key={item.type} type="button" onClick={() => setAccountType(item.type)} className={`p-4 rounded-xl border text-left transition-all ${accountType === item.type ? "border-primary bg-primary/5 shadow-sm" : "border-border hover:bg-muted/50"}`} data-testid={`button-account-type-${item.type}`}>
          <item.icon className={`h-6 w-6 mb-2 ${accountType === item.type ? "text-primary" : "text-muted-foreground"}`} />
          <div className="font-semibold text-sm">{item.label}</div>
          <div className="text-xs text-muted-foreground mt-0.5">{item.desc}</div>
        </button>
            ))}
      </div>

      <form onSubmit={handleRegister} className="space-y-4">
        <div>
          <Label>{accountType === "school" ? "School Name" : "Full Name"}</Label>
          <Input value={name} onChange={e => setName(e.target.value)} placeholder={accountType === "school" ? "Skyline Driving School" : "Your full name"} required data-testid="input-register-name" />
        </div>
        <div>
          <Label>Phone Number</Label>
              <Input type="tel" value={phone} onChange={e => setPhone(e.target.value)} placeholder="+91 98765 43210" data-testid="input-register-phone" />
            </div>
            <div>
              <Label>Email</Label>
              <Input type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="you@example.com" required data-testid="input-register-email" />
            </div>
            <div>
              <Label>Password</Label>
              <Input type="password" value={password} onChange={e => setPassword(e.target.value)} placeholder="Create a password" required data-testid="input-register-password" />
            </div>

            {authError ? <p className="text-sm text-destructive">{authError}</p> : null}

            <Button type="submit" className="w-full" size="lg" data-testid="button-register-submit" disabled={isAuthLoading}>
              {isAuthLoading ? "Creating Account..." : "Create Account"}
            </Button>
            <p className="text-xs text-center text-muted-foreground">By creating an account, you agree to our Terms of Service and Privacy Policy.</p>
          </form>
        </motion.div >
      </div >
    </div >
  );
}
