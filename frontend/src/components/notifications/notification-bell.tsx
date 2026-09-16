import { formatDistanceToNow } from "date-fns";
import {
  Bell,
  Calendar,
  CheckCheck,
  Inbox,
  Loader2,
  Megaphone,
  MessageCircle,
  MessageSquare,
  Star,
  UserPlus,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import { useNotifications } from "@/hooks/use-notifications";
import type { AppNotification } from "@/lib/notifications-api";
import { Button } from "@/components/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from "@/lib/utils";

const TYPE_ICONS: Record<string, LucideIcon> = {
  "inquiry.created": MessageCircle,
  "inquiry.status_changed": Inbox,
  "review.created": Star,
  "review.approved": Star,
  "team.invited": UserPlus,
  "schedule.reminder": Calendar,
  "message.received": MessageSquare,
  "system.announcement": Megaphone,
};

function iconFor(type: string): LucideIcon {
  return TYPE_ICONS[type] ?? Bell;
}

function NotificationRow({
  item,
  onRead,
}: {
  item: AppNotification;
  onRead: (id: string) => void;
}) {
  const Icon = iconFor(item.type);
  const unread = !item.readAt;

  return (
    <button
      type="button"
      onClick={() => {
        if (unread) onRead(item.id);
      }}
      className={cn(
        "w-full text-left px-3 py-2.5 flex gap-3 hover:bg-muted/60 transition-colors border-b last:border-b-0",
        unread && "bg-primary/5",
      )}
    >
      <div
        className={cn(
          "mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full",
          unread ? "bg-primary/15 text-primary" : "bg-muted text-muted-foreground",
        )}
      >
        <Icon className="h-4 w-4" aria-hidden />
      </div>
      <div className="min-w-0 flex-1">
        <div className="flex items-start justify-between gap-2">
          <p className={cn("text-sm leading-snug", unread ? "font-semibold" : "font-medium")}>
            {item.title}
          </p>
          {unread && <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary" aria-hidden />}
        </div>
        {item.body && (
          <p className="text-xs text-muted-foreground mt-0.5 line-clamp-2">{item.body}</p>
        )}
        {item.createdAt && (
          <p className="text-[11px] text-muted-foreground/80 mt-1">
            {formatDistanceToNow(new Date(item.createdAt), { addSuffix: true })}
          </p>
        )}
      </div>
    </button>
  );
}

export function NotificationBell() {
  const {
    notifications,
    unreadCount,
    isLoading,
    isError,
    markRead,
    markAllRead,
    isMarking,
  } = useNotifications();

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          className="relative"
          aria-label={unreadCount > 0 ? `${unreadCount} unread notifications` : "Notifications"}
        >
          <Bell className="h-5 w-5" />
          {unreadCount > 0 && (
            <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-bold text-destructive-foreground">
              {unreadCount > 99 ? "99+" : unreadCount}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80 p-0 sm:w-96">
        <div className="flex items-center justify-between border-b px-3 py-2.5">
          <div className="font-semibold text-sm">Notifications</div>
          <Button
            variant="ghost"
            size="sm"
            className="h-7 text-xs gap-1"
            disabled={unreadCount === 0 || isMarking}
            onClick={() => markAllRead()}
          >
            <CheckCheck className="h-3.5 w-3.5" />
            Mark all read
          </Button>
        </div>

        <ScrollArea className="h-80">
          {isLoading && (
            <div className="flex h-40 items-center justify-center text-muted-foreground">
              <Loader2 className="h-5 w-5 animate-spin" />
            </div>
          )}

          {!isLoading && isError && (
            <div className="flex h-40 items-center justify-center px-4 text-center text-sm text-muted-foreground">
              Could not load notifications.
            </div>
          )}

          {!isLoading && !isError && notifications.length === 0 && (
            <div className="flex h-40 flex-col items-center justify-center gap-2 px-4 text-center text-sm text-muted-foreground">
              <Bell className="h-8 w-8 opacity-40" />
              <p>No notifications yet</p>
            </div>
          )}

          {!isLoading &&
            !isError &&
            notifications.map((item) => (
              <NotificationRow key={item.id} item={item} onRead={markRead} />
            ))}
        </ScrollArea>
      </PopoverContent>
    </Popover>
  );
}
