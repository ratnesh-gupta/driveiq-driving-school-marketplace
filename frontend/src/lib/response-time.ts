/** Formatting for lead response times (DIQ-708). */

export function formatDuration(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined) return "—";
  const m = Math.round(seconds / 60);
  if (m < 1) return "<1 min";
  if (m < 60) return `${m} min`;
  const h = m / 60;
  if (h < 24) return `${h < 10 ? h.toFixed(1).replace(/\.0$/, "") : Math.round(h)} h`;
  const d = h / 24;
  return `${d < 10 ? d.toFixed(1).replace(/\.0$/, "") : Math.round(d)} d`;
}

/** Public badge text; only fast schools get one, slow ones are not advertised. */
export function replyBadge(typicalMinutes: number | null | undefined): string | null {
  if (typicalMinutes === null || typicalMinutes === undefined) return null;
  if (typicalMinutes <= 60) return "Usually replies within an hour";
  if (typicalMinutes <= 240) return "Usually replies within a few hours";
  if (typicalMinutes <= 1440) return "Usually replies within a day";
  return null;
}
