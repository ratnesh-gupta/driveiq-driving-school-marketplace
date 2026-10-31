/**
 * Validates a post-login redirect target (`?next=`). Only same-origin absolute
 * paths are allowed: one leading slash, no backslash, no control characters or
 * whitespace, bounded length. Returns null when unsafe so callers fall back to
 * the role home.
 */
export function safeNextPath(next: string | null | undefined): string | null {
  if (!next || next.length > 512) return null;
  if (!next.startsWith("/") || next.startsWith("//")) return null;
  // eslint-disable-next-line no-control-regex
  if (/[\\\s\u0000-\u001f\u007f]/.test(next)) return null;
  try {
    const url = new URL(next, "http://placeholder.invalid");
    if (url.origin !== "http://placeholder.invalid") return null;
  } catch {
    return null;
  }
  return next;
}
