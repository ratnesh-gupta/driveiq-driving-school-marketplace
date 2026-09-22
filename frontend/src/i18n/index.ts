import { DEFAULT_LOCALE, LOCALE_STORAGE_KEY, type Locale } from "./types";
import en, { type TranslationTree } from "./locales/en";
import hi from "./locales/hi";
import mr from "./locales/mr";

export * from "./types";

const catalogs: Record<Locale, TranslationTree> = { en, hi, mr };

function getByPath(obj: unknown, path: string): string | undefined {
  const parts = path.split(".");
  let cur: unknown = obj;
  for (const p of parts) {
    if (cur == null || typeof cur !== "object") return undefined;
    cur = (cur as Record<string, unknown>)[p];
  }
  return typeof cur === "string" ? cur : undefined;
}

/** Resolve key like `home.heroTitle` with optional {{var}} interpolation. */
export function translate(
  locale: Locale,
  key: string,
  vars?: Record<string, string | number>
): string {
  const raw =
    getByPath(catalogs[locale], key) ??
    getByPath(catalogs[DEFAULT_LOCALE], key) ??
    key;
  if (!vars) return raw;
  return raw.replace(/\{\{(\w+)\}\}/g, (_, name: string) =>
    vars[name] !== undefined ? String(vars[name]) : `{{${name}}}`
  );
}

export function detectInitialLocale(): Locale {
  try {
    const stored = localStorage.getItem(LOCALE_STORAGE_KEY) as Locale | null;
    if (stored && catalogs[stored]) return stored;
  } catch {
    /* ignore */
  }
  if (typeof navigator !== "undefined") {
    const nav = navigator.language?.toLowerCase() ?? "";
    if (nav.startsWith("hi")) return "hi";
    if (nav.startsWith("mr")) return "mr";
  }
  return DEFAULT_LOCALE;
}

export function persistLocale(locale: Locale): void {
  try {
    localStorage.setItem(LOCALE_STORAGE_KEY, locale);
    document.documentElement.lang = locale === "en" ? "en" : locale;
  } catch {
    /* ignore */
  }
}
