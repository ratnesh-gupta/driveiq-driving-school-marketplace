import { useEffect, useRef } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { useAuthStore } from "@/lib/store";
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
  type AppNotification,
} from "@/lib/notifications-api";

const QUERY_KEY = ["notifications"] as const;

export function useNotifications() {
  const token = useAuthStore((s) => s.token);
  const isLoggedIn = useAuthStore((s) => s.isLoggedIn);
  const queryClient = useQueryClient();
  const prevUnread = useRef<number | null>(null);

  const query = useQuery({
    queryKey: [...QUERY_KEY, token],
    queryFn: () => listNotifications(token!, { limit: 30 }),
    enabled: Boolean(isLoggedIn && token),
    refetchInterval: 30_000,
    staleTime: 10_000,
  });

  useEffect(() => {
    const count = query.data?.unreadCount;
    if (typeof count !== "number") return;

    if (prevUnread.current !== null && count > prevUnread.current) {
      const latest = query.data?.data?.find((n) => !n.readAt);
      toast(latest?.title ?? "New notification", {
        description: latest?.body ?? undefined,
      });
    }

    prevUnread.current = count;
  }, [query.data?.unreadCount, query.data?.data]);

  const markRead = useMutation({
    mutationFn: (id: string) => markNotificationRead(token!, id),
    onSuccess: (updated) => {
      queryClient.setQueryData([...QUERY_KEY, token], (old: { data: AppNotification[]; unreadCount: number } | undefined) => {
        if (!old) return old;
        const data = old.data.map((n) => (n.id === updated.id ? updated : n));
        const unreadCount = data.filter((n) => !n.readAt).length;
        return { data, unreadCount };
      });
    },
  });

  const markAllRead = useMutation({
    mutationFn: () => markAllNotificationsRead(token!),
    onSuccess: () => {
      queryClient.setQueryData([...QUERY_KEY, token], (old: { data: AppNotification[]; unreadCount: number } | undefined) => {
        if (!old) return old;
        const now = new Date().toISOString();
        return {
          data: old.data.map((n) => (n.readAt ? n : { ...n, readAt: now })),
          unreadCount: 0,
        };
      });
    },
  });

  return {
    notifications: query.data?.data ?? [],
    unreadCount: query.data?.unreadCount ?? 0,
    isLoading: query.isLoading,
    isError: query.isError,
    refetch: query.refetch,
    markRead: markRead.mutate,
    markAllRead: markAllRead.mutate,
    isMarking: markRead.isPending || markAllRead.isPending,
  };
}
