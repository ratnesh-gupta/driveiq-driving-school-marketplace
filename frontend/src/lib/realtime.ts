/**
 * Realtime channel helper for Laravel Reverb / Echo.
 * Uses window.Echo when configured; otherwise returns a no-op unsubscribe.
 *
 * Env (Vite):
 *   VITE_REVERB_APP_KEY
 *   VITE_REVERB_HOST
 *   VITE_REVERB_PORT
 *   VITE_REVERB_SCHEME  (http|https)
 */

type Handler = (payload: unknown) => void;

declare global {
  interface Window {
    Echo?: {
      private: (channel: string) => {
        listen: (event: string, cb: Handler) => void;
        stopListening: (event: string) => void;
      };
      leave: (channel: string) => void;
      disconnect?: () => void;
    };
  }
}

let echoInitAttempted = false;

export async function ensureEcho(authToken: string | null): Promise<boolean> {
  if (typeof window === "undefined") return false;
  if (window.Echo) return true;
  if (echoInitAttempted) return false;
  echoInitAttempted = true;

  const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;
  if (!key || !authToken) return false;

  try {
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
      import("laravel-echo"),
      import("pusher-js"),
    ]);

    (window as unknown as { Pusher: unknown }).Pusher = Pusher;

    const host = (import.meta.env.VITE_REVERB_HOST as string) || window.location.hostname;
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);
    const scheme = (import.meta.env.VITE_REVERB_SCHEME as string) || "http";

    window.Echo = new Echo({
      broadcaster: "reverb",
      key,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === "https",
      enabledTransports: ["ws", "wss"],
      authEndpoint: `${(import.meta.env.VITE_API_BASE_URL as string)?.replace(/\/api$/i, "") || ""}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${authToken}`,
          Accept: "application/json",
        },
      },
    }) as Window["Echo"];

    return true;
  } catch {
    // Packages may not be installed yet — polling remains active.
    return false;
  }
}

export function subscribePrivate(
  channel: string,
  event: string,
  handler: Handler
): () => void {
  if (!window.Echo) {
    return () => undefined;
  }

  const ch = window.Echo.private(channel);
  ch.listen(event, handler);

  return () => {
    try {
      ch.stopListening(event);
      window.Echo?.leave(channel);
    } catch {
      /* ignore */
    }
  };
}
