/**
 * FSEC-06: one-time tokens (claim, reset, invite, review, unsubscribe) must not
 * linger in the address bar, history or Referer. Read them once, strip them with
 * history.replaceState and keep them in module memory for the flow.
 */
const memory = new Map<string, string>();

/** Read `keys` from the query string, remove them from the URL, return them (memory survives re-renders). */
export function takeQueryParams<K extends string>(scope: string, keys: readonly K[]): Record<K, string> {
  const url = new URL(window.location.href);
  let stripped = false;
  for (const key of keys) {
    const value = url.searchParams.get(key);
    if (value !== null) {
      memory.set(`${scope}:${key}`, value);
      url.searchParams.delete(key);
      stripped = true;
    }
  }
  if (stripped) window.history.replaceState(window.history.state, "", `${url.pathname}${url.search}${url.hash}`);
  return Object.fromEntries(keys.map((k) => [k, memory.get(`${scope}:${k}`) ?? ""])) as Record<K, string>;
}

/** Read the token from `/<prefix>/<token>`, rewrite the URL to `/<prefix>` and return the token. */
export function takePathToken(prefix: string): string {
  const match = window.location.pathname.match(new RegExp(`^/${prefix}/([^/]+)/?$`));
  if (match) {
    memory.set(`path:${prefix}`, decodeURIComponent(match[1]));
    window.history.replaceState(window.history.state, "", `/${prefix}${window.location.search}${window.location.hash}`);
  }
  return memory.get(`path:${prefix}`) ?? "";
}
