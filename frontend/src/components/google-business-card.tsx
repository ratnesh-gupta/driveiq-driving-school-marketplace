import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { BadgeCheck, Star } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useAuthStore } from "@/lib/store";
import { getGetSchoolQueryKey } from "@/api-client";
import {
  connectGoogleBusiness,
  disconnectGoogleBusiness,
  getGoogleBusiness,
  importGoogleLocation,
  listGoogleLocations,
} from "@/lib/acquisition-api";

const RETURN_MESSAGES: Record<string, [string, boolean]> = {
  connected: ["Google Business Profile connected. Choose your location below.", true],
  cancelled: ["Google sign-in was cancelled.", false],
  failed: ["Could not connect to Google. Please try again.", false],
  expired: ["That Google sign-in took too long. Please try again.", false],
};

/**
 * DIQ-1107: owners connect their Google Business Profile and copy their
 * location's details into the listing. Hidden when the server has no
 * Google credentials.
 */
export function GoogleBusinessCard({ schoolId }: { schoolId: number }) {
  const qc = useQueryClient();
  const isOwner = useAuthStore((s) => s.schoolRole) !== "manager";
  const status = useQuery({ queryKey: ["google-business", schoolId], queryFn: () => getGoogleBusiness(schoolId) });
  const [choosing, setChoosing] = useState(false);

  useEffect(() => {
    const result = new URLSearchParams(window.location.search).get("google");
    if (!result) return;
    const [text, ok] = RETURN_MESSAGES[result] ?? ["", true];
    if (text) (ok ? toast.success : toast.error)(text);
    if (result === "connected") setChoosing(true);
    window.history.replaceState(null, "", window.location.pathname);
  }, []);

  const locations = useQuery({
    queryKey: ["google-business", schoolId, "locations"],
    queryFn: () => listGoogleLocations(schoolId),
    enabled: choosing && !!status.data?.connected,
  });

  const connect = useMutation({
    mutationFn: () => connectGoogleBusiness(schoolId),
    onSuccess: (r) => window.location.assign(r.url),
    onError: (e: Error) => toast.error(e.message),
  });
  const importLoc = useMutation({
    mutationFn: (name: string) => importGoogleLocation(schoolId, name),
    onSuccess: (r) => {
      toast.success(r.verified ? "Details imported. Your business is now marked verified." : "Details imported.");
      setChoosing(false);
      void qc.invalidateQueries({ queryKey: ["google-business", schoolId] });
      void qc.invalidateQueries({ queryKey: getGetSchoolQueryKey(schoolId) });
      void qc.invalidateQueries({ queryKey: ["school-dashboard"] });
    },
    onError: (e: Error) => toast.error(e.message),
  });
  const disconnect = useMutation({
    mutationFn: () => disconnectGoogleBusiness(schoolId),
    onSuccess: () => {
      toast.success("Disconnected from Google");
      void qc.invalidateQueries({ queryKey: ["google-business", schoolId] });
    },
  });

  const s = status.data;
  if (!s?.enabled) return null;

  return (
    <div className="rounded-xl border bg-card p-5 space-y-3" data-testid="google-business-card">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 className="font-semibold">Google Business Profile</h2>
          <p className="text-sm text-muted-foreground">
            {s.connected
              ? s.location ? `Linked to ${s.location.title}.` : "Connected. Choose which location to import."
              : "Copy your name, phone, address, map pin and hours from Google. A Google-verified business gets the business-verified check."}
          </p>
          {s.rating != null && (
            <p className="text-sm mt-1 flex items-center gap-1"><Star className="h-4 w-4 fill-yellow-400 text-yellow-400" /> {s.rating.toFixed(1)} on Google · {s.reviewCount ?? 0} reviews</p>
          )}
        </div>
        {isOwner && (
          <div className="flex gap-2">
            {!s.connected ? (
              <Button size="sm" onClick={() => connect.mutate()} disabled={connect.isPending} data-testid="button-google-connect">Connect Google</Button>
            ) : (
              <>
                <Button size="sm" variant="outline" onClick={() => setChoosing(true)} data-testid="button-google-choose">Import details</Button>
                <Button size="sm" variant="ghost" onClick={() => disconnect.mutate()} disabled={disconnect.isPending}>Disconnect</Button>
              </>
            )}
          </div>
        )}
      </div>

      {choosing && s.connected && (
        <div className="space-y-2">
          {locations.isLoading && <p className="text-sm text-muted-foreground">Loading your Google locations…</p>}
          {locations.isError && <p className="text-sm text-destructive">{(locations.error as Error).message}</p>}
          {locations.data && !locations.data.length && <p className="text-sm text-muted-foreground">No locations found in this Google account.</p>}
          {locations.data?.map((l) => (
            <div key={l.name} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm">
              <div>
                <div className="font-medium flex items-center gap-1">
                  {l.title} {l.verified && <BadgeCheck className="h-4 w-4 text-green-600" aria-label="Verified on Google" />}
                </div>
                <div className="text-xs text-muted-foreground">{[l.address, l.phone, l.hours].filter(Boolean).join(" · ")}</div>
              </div>
              <Button size="sm" disabled={importLoc.isPending} onClick={() => window.confirm(`Replace your listing's name, phone, address, map pin and hours with ${l.title}'s?`) && importLoc.mutate(l.name)}>
                Use this one
              </Button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
