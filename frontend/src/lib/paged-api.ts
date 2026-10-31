import { useInfiniteQuery } from "@tanstack/react-query";
import { getStoredToken, handleUnauthorized } from "@/lib/auth-api";
import { HttpError, safeErrorMessage } from "@/lib/errors";

const API_BASE =
  (import.meta.env.VITE_API_BASE_URL as string | undefined)
    ?.replace(/\/+$/, "")
    .replace(/\/api$/i, "") ?? "";

export type Page<T> = { items: T[]; total: number; offset: number };

/**
 * GET a limit/offset list that reports its size in the X-Total-Count header
 * (/api/reviews, /api/packages). The generated client drops response headers,
 * hence this small hand-written reader.
 */
export async function fetchPage<T>(
  path: string,
  params: Record<string, string | number | boolean | undefined>,
  offset: number,
  limit: number,
  signal?: AbortSignal
): Promise<Page<T>> {
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) if (v !== undefined) qs.set(k, String(v));
  qs.set("limit", String(limit));
  qs.set("offset", String(offset));
  const token = getStoredToken();
  const res = await fetch(`${API_BASE}${path}?${qs.toString()}`, {
    signal,
    headers: { Accept: "application/json", ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  });
  if (res.status === 401 && token) handleUnauthorized();
  if (!res.ok) {
    let msg: string | undefined;
    try {
      msg = (await res.json())?.message;
    } catch {
      /* ignore */
    }
    throw new HttpError(res.status, safeErrorMessage(res.status, msg, `Request failed (${res.status})`));
  }
  const items = (await res.json()) as T[];
  const header = Number(res.headers.get("X-Total-Count"));
  const total = Number.isFinite(header) && res.headers.has("X-Total-Count") ? header : offset + items.length;
  return { items, total, offset };
}

/**
 * Load-more list. `queryKey[0]` is the API path so existing
 * invalidateQueries({ queryKey: ["/reviews"] }) calls keep working.
 */
export function usePagedList<T>(
  path: string,
  params: Record<string, string | number | boolean | undefined>,
  opts: { enabled?: boolean; pageSize?: number } = {}
) {
  const pageSize = opts.pageSize ?? 10;
  const query = useInfiniteQuery({
    queryKey: [path, params, "paged", pageSize],
    enabled: opts.enabled ?? true,
    initialPageParam: 0,
    queryFn: ({ pageParam, signal }) => fetchPage<T>(path, params, pageParam, pageSize, signal),
    getNextPageParam: (last) => {
      const next = last.offset + last.items.length;
      return last.items.length > 0 && next < last.total ? next : undefined;
    },
  });
  const pages = query.data?.pages ?? [];
  return {
    items: pages.flatMap((p) => p.items),
    total: pages.length ? pages[pages.length - 1].total : 0,
    isLoading: query.isLoading,
    isError: query.isError,
    hasMore: !!query.hasNextPage,
    loadMore: () => void query.fetchNextPage(),
    isLoadingMore: query.isFetchingNextPage,
  };
}
