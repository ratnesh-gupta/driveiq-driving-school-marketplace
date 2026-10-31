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
        error?: (cb: Handler) => unknown;
      };
      leave: (channel: string) => void;
      disconnect?: () => void;
    };
  }
}

const API_ORIGIN = ((import.meta.env.VITE_API_BASE_URL as string | undefined) ?? "").replace(/\/+$/, "").replace(/\/api$/i, "");

/** Channels the server refused: never retried until the session is torn down. */
const deniedChannels = new Set<string>();

/** Only school staff and admins may join `school.{id}`; instructors and learners use their user channel only. */
export function canJoinSchoolChannel(role: string | null | undefined, schoolId: number | null | undefined): schoolId is number {
  return (role === "school" || role === "admin") && typeof schoolId === "number";
}

let echoInitAttempted = false;
let echoToken: string | null = null;
let echoGeneration = 0;

/** Disconnect and forget the Echo singleton (logout, 401, user switch). */
export function teardownEcho(): void {
  echoGeneration += 1;
  try {
    window.Echo?.disconnect?.();
  } catch {
    /* ignore */
  }
  window.Echo = undefined;
  echoInitAttempted = false;
  echoToken = null;
  deniedChannels.clear();
}

export async function ensureEcho(authToken: string | null): Promise<boolean> {
  if (typeof window === "undefined") return false;
  // A different token means a different user: rebuild rather than reuse.
  if (window.Echo && echoToken !== authToken) teardownEcho();
  if (window.Echo) return true;
  if (echoInitAttempted) return false;
  echoInitAttempted = true;
  const generation = echoGeneration;

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

    // Session ended while the packages were loading: do not build a socket.
    if (generation !== echoGeneration) return false;
    echoToken = authToken;

    window.Echo = new Echo({
      broadcaster: "reverb",
      key,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === "https",
      enabledTransports: ["ws", "wss"],
      // Sanctum bearer auth: POST /api/broadcasting/auth (not the web-session route).
      authEndpoint: `${API_ORIGIN}/api/broadcasting/auth`,
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
    echoInitAttempted = false;
    return false;
  }
}

export function subscribePrivate(
  channel: string,
  event: string,
  handler: Handler
): () => void {
  if (!window.Echo || deniedChannels.has(channel)) {
    return () => undefined;
  }

  const ch = window.Echo.private(channel);
  ch.listen(event, handler);
  // 401/403 from the auth endpoint: give up quietly (no retry loop, no toast).
  ch.error?.(() => {
    deniedChannels.add(channel);
    try {
      ch.stopListening(event);
      window.Echo?.leave(channel);
    } catch {
      /* ignore */
    }
  });

  return () => {
    try {
      ch.stopListening(event);
      window.Echo?.leave(channel);
    } catch {
      /* ignore */
    }
  };
}
