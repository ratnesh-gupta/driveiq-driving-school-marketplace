import { useMemo, useState } from "react";
import {
  addDays,
  addMonths,
  addWeeks,
  endOfMonth,
  endOfWeek,
  format,
  isSameDay,
  isSameMonth,
  isToday,
  startOfMonth,
  startOfWeek,
} from "date-fns";
import { Button } from "@/components/ui/button";
import { ChevronLeft, ChevronRight } from "lucide-react";

export type CalendarSession = {
  id: number;
  sessionDate: string;
  startTime?: string;
  endTime?: string;
  learnerName?: string | null;
  instructorName?: string | null;
  pickupLocation?: string | null;
  status?: string;
};

type View = "week" | "month";

function parseDate(value: string): Date {
  const [y, m, d] = value.slice(0, 10).split("-").map(Number);
  return new Date(y, (m || 1) - 1, d || 1);
}

function statusColor(status?: string) {
  if (status === "completed") return "bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/20";
  if (status === "cancelled") return "bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20";
  if (status === "rescheduled") return "bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/20";
  return "bg-primary/10 text-primary border-primary/20";
}

export function SessionCalendar({
  sessions,
  onSelectSession,
  emptyLabel = "No sessions in this range",
}: {
  sessions: CalendarSession[];
  onSelectSession?: (session: CalendarSession) => void;
  emptyLabel?: string;
}) {
  const [view, setView] = useState<View>("week");
  const [cursor, setCursor] = useState(() => new Date());
  const [selected, setSelected] = useState<Date | null>(new Date());

  const days = useMemo(() => {
    if (view === "week") {
      const start = startOfWeek(cursor, { weekStartsOn: 1 });
      return Array.from({ length: 7 }, (_, i) => addDays(start, i));
    }
    const start = startOfWeek(startOfMonth(cursor), { weekStartsOn: 1 });
    const end = endOfWeek(endOfMonth(cursor), { weekStartsOn: 1 });
    const out: Date[] = [];
    for (let d = start; d <= end; d = addDays(d, 1)) out.push(d);
    return out;
  }, [cursor, view]);

  const byDay = useMemo(() => {
    const map = new Map<string, CalendarSession[]>();
    for (const s of sessions) {
      const key = s.sessionDate?.slice(0, 10);
      if (!key) continue;
      const list = map.get(key) ?? [];
      list.push(s);
      map.set(key, list);
    }
    for (const list of map.values()) {
      list.sort((a, b) => (a.startTime ?? "").localeCompare(b.startTime ?? ""));
    }
    return map;
  }, [sessions]);

  const title =
    view === "week"
      ? `${format(days[0], "d MMM")} – ${format(days[days.length - 1], "d MMM yyyy")}`
      : format(cursor, "MMMM yyyy");

  const selectedKey = selected ? format(selected, "yyyy-MM-dd") : null;
  const selectedSessions = selectedKey ? byDay.get(selectedKey) ?? [] : [];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setCursor(view === "week" ? addWeeks(cursor, -1) : addMonths(cursor, -1))}
          >
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <div className="min-w-[180px] text-center font-semibold">{title}</div>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setCursor(view === "week" ? addWeeks(cursor, 1) : addMonths(cursor, 1))}
          >
            <ChevronRight className="h-4 w-4" />
          </Button>
          <Button variant="outline" size="sm" onClick={() => { setCursor(new Date()); setSelected(new Date()); }}>
            Today
          </Button>
        </div>
        <div className="inline-flex rounded-lg border p-0.5 bg-muted/40">
          {(["week", "month"] as View[]).map((v) => (
            <Button
              key={v}
              size="sm"
              variant={view === v ? "secondary" : "ghost"}
              className="capitalize"
              onClick={() => setView(v)}
            >
              {v}
            </Button>
          ))}
        </div>
      </div>

      <div className="grid grid-cols-7 gap-1 text-center text-[11px] text-muted-foreground px-1">
        {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => (
          <div key={d}>{d}</div>
        ))}
      </div>

      {view === "month" ? (
        <div className="grid grid-cols-7 gap-1">
          {days.map((day) => {
            const key = format(day, "yyyy-MM-dd");
            const items = byDay.get(key) ?? [];
            const inMonth = isSameMonth(day, cursor);
            const active = selected && isSameDay(day, selected);
            return (
              <button
                key={key}
                type="button"
                onClick={() => setSelected(day)}
                className={`min-h-[88px] rounded-xl border p-1.5 text-left transition hover:bg-muted/50 ${
                  active ? "ring-2 ring-primary border-primary" : ""
                } ${!inMonth ? "opacity-40" : ""} ${isToday(day) ? "bg-primary/5" : "bg-card"}`}
              >
                <div className={`text-xs font-medium mb-1 ${isToday(day) ? "text-primary" : ""}`}>
                  {format(day, "d")}
                </div>
                <div className="space-y-1">
                  {items.slice(0, 3).map((s) => (
                    <div
                      key={s.id}
                      className={`truncate rounded-md border px-1 py-0.5 text-[10px] leading-tight ${statusColor(s.status)}`}
                    >
                      {s.startTime} {s.learnerName || s.instructorName || ""}
                    </div>
                  ))}
                  {items.length > 3 && (
                    <div className="text-[10px] text-muted-foreground">+{items.length - 3} more</div>
                  )}
                </div>
              </button>
            );
          })}
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-7 gap-2">
          {days.map((day) => {
            const key = format(day, "yyyy-MM-dd");
            const items = byDay.get(key) ?? [];
            const active = selected && isSameDay(day, selected);
            return (
              <button
                key={key}
                type="button"
                onClick={() => setSelected(day)}
                className={`rounded-xl border p-3 text-left min-h-[220px] transition hover:bg-muted/40 ${
                  active ? "ring-2 ring-primary border-primary" : ""
                } ${isToday(day) ? "bg-primary/5" : "bg-card"}`}
              >
                <div className="flex items-baseline justify-between mb-2">
                  <span className={`text-sm font-semibold ${isToday(day) ? "text-primary" : ""}`}>
                    {format(day, "EEE")}
                  </span>
                  <span className="text-xs text-muted-foreground">{format(day, "d MMM")}</span>
                </div>
                <div className="space-y-2">
                  {items.length === 0 && (
                    <div className="text-[11px] text-muted-foreground">Free</div>
                  )}
                  {items.map((s) => (
                    <div
                      key={s.id}
                      role="button"
                      tabIndex={0}
                      onClick={(e) => {
                        e.stopPropagation();
                        onSelectSession?.(s);
                        setSelected(parseDate(s.sessionDate));
                      }}
                      className={`rounded-lg border px-2 py-1.5 text-xs ${statusColor(s.status)}`}
                    >
                      <div className="font-medium">{s.startTime}–{s.endTime}</div>
                      <div className="truncate opacity-80">{s.learnerName || s.instructorName || s.status}</div>
                    </div>
                  ))}
                </div>
              </button>
            );
          })}
        </div>
      )}

      <div className="rounded-xl border bg-card">
        <div className="px-4 py-3 border-b text-sm font-medium">
          {selected ? format(selected, "EEEE, d MMMM") : "Select a day"}
        </div>
        {!selectedSessions.length ? (
          <div className="py-8 text-center text-sm text-muted-foreground">{emptyLabel}</div>
        ) : (
          <div className="divide-y">
            {selectedSessions.map((s) => (
              <button
                key={s.id}
                type="button"
                className="w-full text-left px-4 py-3 hover:bg-muted/40 flex items-center justify-between gap-3"
                onClick={() => onSelectSession?.(s)}
              >
                <div>
                  <div className="text-sm font-medium">{s.startTime}–{s.endTime} · {s.learnerName || s.instructorName || "Session"}</div>
                  <div className="text-xs text-muted-foreground">{s.pickupLocation || "—"}</div>
                </div>
                <span className={`text-[11px] px-2 py-0.5 rounded-full border ${statusColor(s.status)}`}>{s.status}</span>
              </button>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
