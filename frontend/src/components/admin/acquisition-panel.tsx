import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Skeleton } from "@/components/ui/skeleton";
import { getAcquisitionStats } from "@/lib/acquisition-api";

function Tile({ label, value, sub }: { label: string; value: string | number; sub?: string }) {
  return (
    <div className="rounded-lg bg-muted/40 p-3">
      <div className="text-xl font-bold tabular-nums">{value}</div>
      <div className="text-xs font-medium">{label}</div>
      {sub && <div className="text-[11px] text-muted-foreground">{sub}</div>}
    </div>
  );
}

const SOURCE_LABEL: Record<string, string> = { organic: "Found us", outreach: "Outreach email", ads: "Google Ads", gbp: "Google Business" };

/** DIQ-1109: how schools and trainers are coming on board, and where supply is thin. */
export function AcquisitionPanel() {
  const [days, setDays] = useState(30);
  const { data, isLoading } = useQuery({ queryKey: ["admin", "acquisition", days], queryFn: () => getAcquisitionStats(days) });

  return (
    <div className="rounded-xl border bg-card p-5 mb-6" data-testid="acquisition-panel">
      <div className="flex items-center justify-between mb-3">
        <h2 className="font-semibold">Coming on board</h2>
        <select className="h-8 rounded-md border bg-background px-2 text-sm" value={days} onChange={(e) => setDays(Number(e.target.value))} aria-label="Period">
          {[7, 30, 90, 365].map((d) => <option key={d} value={d}>Last {d} days</option>)}
        </select>
      </div>
      {isLoading || !data ? (
        <Skeleton className="h-24" />
      ) : (
        <>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
            <Tile label="Outreach emails" value={data.outreach.sent} sub={`${data.outreach.clicked} opened the link`} />
            <Tile label="Claimed from email" value={data.outreach.claimed} sub={`${data.outreach.unsubscribed} unsubscribed`} />
            <Tile label="Google Ads leads" value={data.ads.leads} sub={`${data.ads.signedUp} signed up`} />
            <Tile label="Joined" value={data.listings.joined} sub={Object.entries(data.listings.bySource).map(([s, n]) => `${n} ${SOURCE_LABEL[s] ?? s}`).join(" · ") || "—"} />
            <Tile label="Went live" value={data.listings.published.schools + data.listings.published.trainers} sub={`${data.listings.published.schools} schools · ${data.listings.published.trainers} trainers`} />
            <Tile
              label="Time to go live"
              value={data.listings.medianHoursToPublish == null ? "—" : data.listings.medianHoursToPublish < 48 ? `${data.listings.medianHoursToPublish} h` : `${Math.round(data.listings.medianHoursToPublish / 24)} d`}
              sub={`${data.listings.waitingToPublish} drafts waiting`}
            />
          </div>
          <details className="mt-4">
            <summary className="text-sm cursor-pointer text-muted-foreground">Live listings by locality (all time)</summary>
            <table className="w-full text-sm mt-2">
              <thead><tr className="text-left text-xs text-muted-foreground"><th className="py-1">Locality</th><th>Schools</th><th>Trainers</th></tr></thead>
              <tbody className="divide-y">
                {data.supply.map((r) => (
                  <tr key={r.localityId} className={r.schools + r.trainers === 0 ? "text-destructive" : ""}>
                    <td className="py-1">{r.locality}</td>
                    <td className="tabular-nums">{r.schools}</td>
                    <td className="tabular-nums">{r.trainers}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <p className="text-[11px] text-muted-foreground mt-1">Localities in red have nobody live yet: good places to start outreach.</p>
          </details>
        </>
      )}
    </div>
  );
}
