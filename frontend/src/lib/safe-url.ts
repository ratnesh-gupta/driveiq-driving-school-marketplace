/**
 * Allowlisting helpers for URLs built from data (FSEC-10/11). Return null when
 * the value is unusable; call sites then render no link instead of a bad one.
 */

const API_ORIGIN = (() => {
  const base = (import.meta.env.VITE_API_BASE_URL as string | undefined)?.replace(/\/+$/, "").replace(/\/api$/i, "");
  if (!base) return null;
  try {
    return new URL(base).origin;
  } catch {
    return null;
  }
})();

const EMAIL_RE = /^[^\s@<>,;:"'\\()[\]]+@[^\s@<>,;:"'\\()[\]]+\.[^\s@<>,;:"'\\()[\]]+$/;

/** Phone number reduced to an optional leading "+" and digits (7-15 digits). */
export function normalizePhone(raw: string | null | undefined): string | null {
  if (!raw) return null;
  const digits = raw.replace(/\D/g, "");
  if (digits.length < 7 || digits.length > 15) return null;
  return `${raw.trim().startsWith("+") ? "+" : ""}${digits}`;
}

export function telHref(raw: string | null | undefined): string | null {
  const n = normalizePhone(raw);
  return n ? `tel:${n}` : null;
}

export function mailtoHref(email: string | null | undefined, subject?: string): string | null {
  const e = email?.trim();
  if (!e || e.length > 254 || !EMAIL_RE.test(e)) return null;
  const q = subject ? `?subject=${encodeURIComponent(subject)}` : "";
  return `mailto:${e}${q}`;
}

/** wa.me link: digits only. Numbers without a country code are left as given (backend stores them). */
export function whatsappHref(raw: string | null | undefined, text?: string): string | null {
  const digits = raw?.replace(/\D/g, "") ?? "";
  if (digits.length < 7 || digits.length > 15) return null;
  const q = text ? `?text=${encodeURIComponent(text)}` : "";
  return `https://wa.me/${digits}${q}`;
}

/**
 * URL for navigation or window.open from an API response. https only (plain
 * http solely for the API's own origin, i.e. local dev), no credentials in the
 * URL, optional host allowlist (exact host or subdomain).
 */
export function safeNavigationUrl(raw: string | null | undefined, allowedHosts?: string[]): string | null {
  if (!raw) return null;
  let url: URL;
  try {
    url = new URL(raw);
  } catch {
    return null;
  }
  if (url.username || url.password) return null;
  const sameApi = API_ORIGIN !== null && url.origin === API_ORIGIN;
  if (url.protocol !== "https:" && !(url.protocol === "http:" && sameApi)) return null;
  if (allowedHosts && !sameApi) {
    const host = url.hostname.toLowerCase();
    if (!allowedHosts.some((h) => host === h || host.endsWith(`.${h}`))) return null;
  }
  return url.toString();
}

/** Hosts the backend may send users to for Google OAuth. */
export const GOOGLE_OAUTH_HOSTS = ["accounts.google.com"];
