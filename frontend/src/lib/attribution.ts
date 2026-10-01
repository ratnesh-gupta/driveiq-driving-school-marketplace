/**
 * First-visit marketing tags (DIQ-1108). Kept only in this browser and sent
 * once, when a school or trainer registers, so we can tell which channel
 * (outreach email, Google Ads, organic) brought them. No personal data.
 */
const KEY = "driveiq_attribution";
const FIELDS = ["utm_source", "utm_medium", "utm_campaign"] as const;

export function captureAttribution(search: string = window.location.search): void {
  const params = new URLSearchParams(search);
  const tags = Object.fromEntries(FIELDS.map((f) => [f, params.get(f)?.slice(0, 100) ?? ""]).filter(([, v]) => v));
  if (!Object.keys(tags).length) return;
  try {
    // First touch wins: a later visit does not overwrite it.
    if (!localStorage.getItem(KEY)) localStorage.setItem(KEY, JSON.stringify(tags));
  } catch {
    /* storage blocked: attribution is optional */
  }
}

export function getAttribution(): Record<string, string> | undefined {
  try {
    const raw = localStorage.getItem(KEY);
    return raw ? (JSON.parse(raw) as Record<string, string>) : undefined;
  } catch {
    return undefined;
  }
}
