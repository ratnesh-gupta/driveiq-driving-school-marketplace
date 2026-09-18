import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { getMessageThread, listMessageThreads, sendMessage } from "@/lib/ops-api";
import { MessageSquare } from "lucide-react";
import { toast } from "sonner";

export default function SchoolMessagesPage() {
  return (
    <DashboardLayout>
      <MessagesPanel title="Messages" />
    </DashboardLayout>
  );
}

export function MessagesPanel({ title }: { title: string }) {
  const qc = useQueryClient();
  const [activeId, setActiveId] = useState<number | null>(null);
  const [draft, setDraft] = useState("");

  const threads = useQuery({
    queryKey: ["messages", "threads"],
    queryFn: listMessageThreads,
    refetchInterval: 20_000,
  });

  const thread = useQuery({
    queryKey: ["messages", "thread", activeId],
    queryFn: () => getMessageThread(activeId!),
    enabled: !!activeId,
  });

  const send = useMutation({
    mutationFn: () => sendMessage({ threadId: activeId!, body: draft }),
    onSuccess: () => {
      setDraft("");
      void qc.invalidateQueries({ queryKey: ["messages"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">{title}</h1>
        <p className="text-sm text-muted-foreground mt-1">In-app conversations (reduces WhatsApp dependency)</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 min-h-[420px]">
        <div className="rounded-xl border bg-card md:col-span-1 overflow-hidden">
          {threads.isLoading ? (
            <div className="p-3 space-y-2">{Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-12" />)}</div>
          ) : !threads.data?.length ? (
            <div className="py-12 text-center text-sm text-muted-foreground">
              <MessageSquare className="h-8 w-8 mx-auto mb-2 opacity-30" />
              No threads yet
            </div>
          ) : (
            <div className="divide-y max-h-[520px] overflow-y-auto">
              {threads.data.map((t) => (
                <button
                  key={t.id}
                  type="button"
                  onClick={() => setActiveId(t.id)}
                  className={`w-full text-left px-4 py-3 hover:bg-muted/50 ${activeId === t.id ? "bg-muted" : ""}`}
                >
                  <div className="flex justify-between gap-2">
                    <span className="font-medium text-sm">{t.otherUser?.name ?? "User"}</span>
                    {t.unreadCount > 0 && (
                      <span className="text-[10px] bg-primary text-primary-foreground rounded-full px-1.5">{t.unreadCount}</span>
                    )}
                  </div>
                  <div className="text-xs text-muted-foreground truncate">{t.lastMessagePreview || "—"}</div>
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="rounded-xl border bg-card md:col-span-2 flex flex-col min-h-[320px]">
          {!activeId ? (
            <div className="flex-1 flex items-center justify-center text-sm text-muted-foreground">Select a conversation</div>
          ) : thread.isLoading ? (
            <div className="p-4 space-y-2">{Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-10" />)}</div>
          ) : (
            <>
              <div className="px-4 py-3 border-b font-medium text-sm">
                {thread.data?.otherUser?.name} · {thread.data?.otherUser?.role}
              </div>
              <div className="flex-1 overflow-y-auto p-4 space-y-3 max-h-[360px]">
                {thread.data?.messages.map((m) => (
                  <div key={m.id} className="text-sm">
                    <div className="text-xs text-muted-foreground mb-0.5">{m.senderName}</div>
                    <div className="rounded-lg bg-muted px-3 py-2 inline-block max-w-[85%]">{m.body}</div>
                  </div>
                ))}
              </div>
              <div className="p-3 border-t flex gap-2">
                <Input
                  value={draft}
                  onChange={(e) => setDraft(e.target.value)}
                  placeholder="Type a message…"
                  onKeyDown={(e) => {
                    if (e.key === "Enter" && draft.trim()) send.mutate();
                  }}
                />
                <Button disabled={!draft.trim() || send.isPending} onClick={() => send.mutate()}>Send</Button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
