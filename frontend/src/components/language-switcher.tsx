import { LOCALES, type Locale } from "@/i18n/types";
import { useLocaleStore } from "@/i18n/use-locale";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Languages } from "lucide-react";

export function LanguageSwitcher({
  variant = "ghost",
  compact = false,
}: {
  variant?: "ghost" | "outline" | "secondary";
  compact?: boolean;
}) {
  const locale = useLocaleStore((s) => s.locale);
  const setLocale = useLocaleStore((s) => s.setLocale);
  const current = LOCALES.find((l) => l.code === locale) ?? LOCALES[0];

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant={variant}
          size={compact ? "icon" : "sm"}
          className={compact ? undefined : "gap-1.5"}
          aria-label="Language"
        >
          <Languages className="h-4 w-4" />
          {!compact && <span className="hidden sm:inline text-xs">{current.nativeLabel}</span>}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {LOCALES.map((l) => (
          <DropdownMenuItem
            key={l.code}
            onClick={() => setLocale(l.code as Locale)}
            className={locale === l.code ? "bg-accent" : ""}
          >
            <span className="font-medium">{l.nativeLabel}</span>
            <span className="ml-2 text-muted-foreground text-xs">{l.label}</span>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
