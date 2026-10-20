import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { MessageCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { fetchMyWhatsApp, saveMyWhatsApp } from "@/lib/ops-api";

const COPY = {
  staff: "Get new-lead alerts and unanswered-lead reminders on WhatsApp, when your school has WhatsApp alerts on.",
  trainer: "Get reminders 24 hours and 2 hours before your sessions on WhatsApp.",
  learner: "Get reminders 24 hours and 2 hours before your driving sessions on WhatsApp.",
};

/**
 * A person's own WhatsApp number and opt-in (DIQ-1002). Only they can turn it
 * on; turning it off takes effect immediately and is recorded.
 */
export function WhatsAppOptInCard({ audience }: { audience: keyof typeof COPY }) {
  const qc = useQueryClient();
  const { data } = useQuery({ queryKey: ["me", "whatsapp"], queryFn: fetchMyWhatsApp });
  const [phone, setPhone] = useState("");
  useEffect(() => {
    if (data) setPhone(data.phone ?? "");
  }, [data]);

  const save = useMutation({
    mutationFn: (optIn: boolean) => saveMyWhatsApp({ phone: phone.trim() || null, optIn }),
    onSuccess: (res) => {
      qc.setQueryData(["me", "whatsapp"], res);
      toast.success(res.optedIn ? "WhatsApp updates on" : "WhatsApp updates off");
    },
    onError: (e: Error) => toast.error(e.message),
  });

  if (!data) return null;
  const phoneChanged = (data.phone ?? "") !== phone.trim();

  return (
    <section className="rounded-xl border bg-card p-5" data-testid="whatsapp-opt-in">
      <h2 className="font-semibold flex items-center gap-2"><MessageCircle className="h-4 w-4" /> My WhatsApp updates</h2>
      <p className="text-xs text-muted-foreground mt-1">{COPY[audience]} You can turn this off at any time.</p>
      <div className="mt-3 flex flex-wrap items-end gap-3">
        <div className="flex-1 min-w-48">
          <Label htmlFor="wa-phone">WhatsApp number</Label>
          <Input id="wa-phone" inputMode="tel" placeholder="98765 43210" value={phone} onChange={(e) => setPhone(e.target.value)} />
        </div>
        {data.optedIn && phoneChanged && (
          <Button variant="outline" disabled={save.isPending} onClick={() => save.mutate(true)}>Save number</Button>
        )}
        <label className="flex items-center gap-2 text-sm pb-2">
          <Switch
            checked={data.optedIn}
            disabled={save.isPending || (!data.optedIn && !phone.trim())}
            onCheckedChange={(v) => save.mutate(v)}
            data-testid="switch-whatsapp-opt-in"
          />
          {data.optedIn ? "On" : "Off"}
        </label>
      </div>
    </section>
  );
}
