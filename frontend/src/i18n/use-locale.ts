import { create } from "zustand";
import { detectInitialLocale, persistLocale, translate } from "./index";
import type { Locale } from "./types";

interface LocaleState {
  locale: Locale;
  setLocale: (locale: Locale) => void;
}

export const useLocaleStore = create<LocaleState>((set) => ({
  locale: detectInitialLocale(),
  setLocale: (locale) => {
    persistLocale(locale);
    set({ locale });
  },
}));

/** Hook: t('home.heroTitle') or t('search.found', { count: 3 }) */
export function useT() {
  const locale = useLocaleStore((s) => s.locale);
  return (key: string, vars?: Record<string, string | number>) =>
    translate(locale, key, vars);
}
