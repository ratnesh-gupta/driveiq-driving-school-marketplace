import { Link } from "wouter";
import { useAuthStore, useThemeStore } from "@/lib/store";
import { Button } from "@/components/ui/button";
import { Moon, Sun, Menu } from "lucide-react";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { LanguageSwitcher } from "@/components/language-switcher";
import { useT } from "@/i18n/use-locale";

export function PublicLayout({ children }: { children: React.ReactNode }) {
  const { isLoggedIn, logout, userRole } = useAuthStore();
  const { theme, setTheme } = useThemeStore();
  const t = useT();

  return (
    <div className="min-h-[100dvh] flex flex-col">
      <header className="sticky top-0 z-50 w-full border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div className="container mx-auto px-4 h-16 flex items-center justify-between">
          <div className="flex items-center gap-6">
            <Link href="/" className="font-bold text-xl text-primary tracking-tight">DriveIQ</Link>
            <nav className="hidden md:flex gap-4">
              <Link href="/search" className="text-sm font-medium text-muted-foreground hover:text-primary transition-colors">{t("nav.search")}</Link>
              <Link href="/driving-rules" className="text-sm font-medium text-muted-foreground hover:text-primary transition-colors">{t("nav.drivingRules")}</Link>
              <Link href="/about" className="text-sm font-medium text-muted-foreground hover:text-primary transition-colors">{t("nav.about")}</Link>
              <Link href="/contact" className="text-sm font-medium text-muted-foreground hover:text-primary transition-colors">{t("nav.contact")}</Link>
            </nav>
          </div>

          <div className="flex items-center gap-2 sm:gap-4">
            <LanguageSwitcher />
            <Button variant="ghost" size="icon" onClick={() => setTheme(theme === "dark" ? "light" : "dark")}>
              <Sun className="h-5 w-5 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
              <Moon className="absolute h-5 w-5 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
              <span className="sr-only">Toggle theme</span>
            </Button>

            <div className="hidden md:flex items-center gap-4">
              {isLoggedIn ? (
                <>
                  <Link href={userRole === "admin" ? "/admin" : userRole === "school" ? "/dashboard" : "/search"}>
                    <Button variant="outline">{t("common.dashboard")}</Button>
                  </Link>
                  <Button variant="ghost" onClick={() => { void logout(); }}>{t("common.logout")}</Button>
                </>
              ) : (
                <>
                  <Link href="/auth/login"><Button variant="ghost">{t("common.login")}</Button></Link>
                  <Link href="/auth/register"><Button>{t("common.signup")}</Button></Link>
                </>
              )}
            </div>

            <Sheet>
              <SheetTrigger asChild>
                <Button variant="outline" size="icon" className="md:hidden">
                  <Menu className="h-5 w-5" />
                </Button>
              </SheetTrigger>
              <SheetContent side="right">
                <nav className="flex flex-col gap-4 mt-8">
                  <Link href="/search" className="text-sm font-medium">{t("nav.search")}</Link>
                  <Link href="/driving-rules" className="text-sm font-medium">{t("nav.drivingRules")}</Link>
                  <Link href="/about" className="text-sm font-medium">{t("nav.about")}</Link>
                  <Link href="/contact" className="text-sm font-medium">{t("nav.contact")}</Link>
                  <Link href="/privacy" className="text-sm font-medium">{t("common.privacy")}</Link>
                  <Link href="/terms" className="text-sm font-medium">{t("common.terms")}</Link>
                  <hr className="my-2" />
                  {isLoggedIn ? (
                    <>
                      <Link href={userRole === "admin" ? "/admin" : userRole === "school" ? "/dashboard" : "/search"} className="text-sm font-medium">{t("common.dashboard")}</Link>
                      <button onClick={() => { void logout(); }} className="text-sm font-medium text-left text-destructive">{t("common.logout")}</button>
                    </>
                  ) : (
                    <>
                      <Link href="/auth/login" className="text-sm font-medium">{t("common.login")}</Link>
                      <Link href="/auth/register" className="text-sm font-medium">{t("common.signup")}</Link>
                    </>
                  )}
                </nav>
              </SheetContent>
            </Sheet>
          </div>
        </div>
      </header>

      <main className="flex-1">{children}</main>

      <footer className="border-t bg-muted/40 py-12">
        <div className="container mx-auto px-4 grid grid-cols-1 md:grid-cols-4 gap-8">
          <div>
            <h3 className="font-bold text-lg mb-4">DriveIQ</h3>
            <p className="text-sm text-muted-foreground">{t("nav.tagline")}</p>
          </div>
          <div>
            <h4 className="font-semibold mb-4">{t("nav.discover")}</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><Link href="/search" className="hover:text-primary">{t("nav.searchSchools")}</Link></li>
              <li><Link href="/driving-rules" className="hover:text-primary">{t("nav.drivingRules")}</Link></li>
              <li><Link href="/search?locality=baner" className="hover:text-primary">Baner</Link></li>
              <li><Link href="/search?locality=hinjewadi" className="hover:text-primary">Hinjewadi</Link></li>
            </ul>
          </div>
          <div>
            <h4 className="font-semibold mb-4">{t("nav.company")}</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><Link href="/about" className="hover:text-primary">{t("nav.about")}</Link></li>
              <li><Link href="/contact" className="hover:text-primary">{t("nav.contact")}</Link></li>
            </ul>
          </div>
          <div>
            <h4 className="font-semibold mb-4">Legal</h4>
            <ul className="space-y-2 text-sm text-muted-foreground">
              <li><Link href="/privacy" className="hover:text-primary">{t("legal.privacy")}</Link></li>
              <li><Link href="/terms" className="hover:text-primary">{t("legal.terms")}</Link></li>
              <li><Link href="/privacy/data-request" className="hover:text-primary">{t("legal.dataRights")}</Link></li>
              <li><Link href="/auth/register" className="hover:text-primary">{t("nav.partnerWithUs")}</Link></li>
              <li><Link href="/auth/login" className="hover:text-primary">{t("nav.schoolLogin")}</Link></li>
            </ul>
          </div>
        </div>
        <div className="container mx-auto px-4 mt-8 pt-8 border-t text-center text-sm text-muted-foreground">
          &copy; {new Date().getFullYear()} DriveIQ
          {" · "}
          <Link href="/privacy" className="hover:text-primary">{t("common.privacy")}</Link>
          {" · "}
          <Link href="/terms" className="hover:text-primary">{t("common.terms")}</Link>
        </div>
      </footer>
    </div>
  );
}
