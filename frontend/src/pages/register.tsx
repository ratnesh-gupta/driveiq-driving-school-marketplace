import { useState } from "react";
import { useLocation, Link } from "wouter";
import { motion } from "framer-motion";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAuthStore } from "@/lib/store";
import { Users, Building2 } from "lucide-react";
import { LanguageSwitcher } from "@/components/language-switcher";
import { useT } from "@/i18n/use-locale";

type AccountType = "user" | "school";

function FieldError({ errors }: { errors?: string[] }) {
  if (!errors?.length) return null;
  return (
    <div className="mt-1 space-y-0.5">
      {errors.map((msg) => (
        <p key={msg} className="text-xs text-destructive">{msg}</p>
      ))}
    </div>
  );
}

export default function RegisterPage() {
  const [accountType, setAccountType] = useState<AccountType>("school");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const { register, isAuthLoading, authError, fieldErrors, clearAuthErrors, userRole } = useAuthStore();
  const [, setLocation] = useLocation();
  const t = useT();

  const handleRegister = async (e: React.FormEvent) => {
    e.preventDefault();
    clearAuthErrors();
    try {
      await register({ name, email, password, role: accountType });
      const role = useAuthStore.getState().userRole ?? userRole;
      setLocation(role === "school" ? "/dashboard" : "/search");
    } catch {
      // errors are in the store
    }
  };

  const hasFieldErrors = Object.keys(fieldErrors).length > 0;

  return (
    <div className="min-h-screen grid grid-cols-1 lg:grid-cols-2">
      <div className="hidden lg:flex flex-col bg-gradient-to-br from-[hsl(258,60%,18%)] via-[hsl(221,83%,20%)] to-[hsl(221,83%,12%)] text-white p-12 relative overflow-hidden">
        <div className="flex items-center justify-between z-10">
          <Link href="/" className="font-bold text-2xl tracking-tight">DriveIQ</Link>
          <LanguageSwitcher variant="outline" />
        </div>
        <div className="flex-1 flex flex-col justify-center z-10 max-w-sm">
          <motion.div initial={{ opacity: 0, y: 30 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.6 }}>
            <h2 className="text-4xl font-bold mb-4">{t("auth.joinTitle")}</h2>
            <p className="text-white/70 text-lg leading-relaxed">{t("auth.joinSub")}</p>
          </motion.div>
        </div>
        <div className="text-white/40 text-xs z-10">&copy; {new Date().getFullYear()} DriveIQ</div>
      </div>

      <div className="flex items-center justify-center p-8 bg-background relative">
        <div className="absolute top-4 right-4 lg:hidden"><LanguageSwitcher /></div>
        <motion.div className="w-full max-w-sm" initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.5 }}>
          <div className="mb-8">
            <Link href="/" className="font-bold text-xl text-primary lg:hidden">DriveIQ</Link>
            <h1 className="text-2xl font-bold mt-4">{t("auth.createAccount")}</h1>
            <p className="text-muted-foreground text-sm mt-1">{t("auth.hasAccount")} <Link href="/auth/login" className="text-primary hover:underline">{t("common.login")}</Link></p>
          </div>

          <div className="grid grid-cols-2 gap-3 mb-6">
            {([
              { type: "school" as const, icon: Building2, label: t("auth.schoolType"), desc: t("auth.schoolDesc") },
              { type: "user" as const, icon: Users, label: t("auth.learnerType"), desc: t("auth.learnerDesc") },
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
              <Label>{accountType === "school" ? t("auth.schoolName") : t("auth.fullName")}</Label>
              <Input value={name} onChange={e => setName(e.target.value)} required data-testid="input-register-name" />
              <FieldError errors={fieldErrors.name} />
            </div>
            <div>
              <Label>{t("auth.phone")}</Label>
              <Input type="tel" value={phone} onChange={e => setPhone(e.target.value)} data-testid="input-register-phone" />
              <FieldError errors={fieldErrors.phone} />
            </div>
            <div>
              <Label>{t("auth.email")}</Label>
              <Input type="email" value={email} onChange={e => setEmail(e.target.value)} required data-testid="input-register-email" />
              <FieldError errors={fieldErrors.email} />
            </div>
            <div>
              <Label>{t("auth.password")}</Label>
              <Input type="password" value={password} onChange={e => setPassword(e.target.value)} required data-testid="input-register-password" />
              <FieldError errors={fieldErrors.password} />
            </div>

            {authError && !hasFieldErrors ? <p className="text-sm text-destructive">{authError}</p> : null}

            <Button type="submit" className="w-full" size="lg" data-testid="button-register-submit" disabled={isAuthLoading}>
              {isAuthLoading ? t("auth.creating") : t("auth.createBtn")}
            </Button>
            <p className="text-xs text-center text-muted-foreground">
              <Link href="/privacy" className="underline">{t("legal.privacy")}</Link>
              {" · "}
              <Link href="/terms" className="underline">{t("legal.terms")}</Link>
            </p>
          </form>
        </motion.div>
      </div>
    </div>
  );
}
