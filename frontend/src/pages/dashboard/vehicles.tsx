import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { PlanGateBanner } from "@/components/plan/plan-gate-banner";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { DocumentsPanel } from "@/components/documents-panel";
import { useEntitlements } from "@/hooks/use-entitlements";
import { useSchoolId } from "@/hooks/use-school-id";
import { createVehicle, listVehicles, updateVehicle, type VehicleRow } from "@/lib/ops-api";
import { AlertTriangle, Car, ChevronRight, Plus } from "lucide-react";

const SELECT = "w-full h-9 rounded-md border bg-background px-2 text-sm";
const STATUS_TONE: Record<string, string> = {
  active: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  maintenance: "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400",
  retired: "bg-muted text-muted-foreground",
};

type Form = { registrationNumber: string; type: string; transmission: string; fuelType: string; makeModel: string; year: string; status: string; notes: string };
const empty: Form = { registrationNumber: "", type: "car", transmission: "manual", fuelType: "petrol", makeModel: "", year: "", status: "active", notes: "" };

function toForm(v: VehicleRow): Form {
  return {
    registrationNumber: v.registrationNumber, type: v.type, transmission: v.transmission ?? "", fuelType: v.fuelType ?? "",
    makeModel: v.makeModel ?? "", year: v.year ? String(v.year) : "", status: v.status, notes: v.notes ?? "",
  };
}

/** Fleet: add, edit, send to maintenance or retire, and keep papers current (DIQ-909). */
export default function VehiclesPage() {
  const canWrite = useEntitlements().has("vehicles");
  const schoolId = useSchoolId();
  const qc = useQueryClient();
  const [sheet, setSheet] = useState<{ open: boolean; id: number | null }>({ open: false, id: null });
  const [form, setForm] = useState<Form>(empty);

  const { data, isLoading } = useQuery({
    queryKey: ["vehicles", schoolId],
    queryFn: () => listVehicles(schoolId!) as Promise<VehicleRow[]>,
    enabled: !!schoolId,
  });
  const current = sheet.id !== null ? data?.find((v) => v.id === sheet.id) ?? null : null;

  useEffect(() => {
    if (sheet.open) setForm(current ? toForm(current) : empty);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sheet.open, sheet.id]);

  const save = useMutation({
    mutationFn: () => {
      const body = {
        registrationNumber: form.registrationNumber.trim(),
        type: form.type,
        transmission: form.transmission || null,
        fuelType: form.fuelType || null,
        makeModel: form.makeModel.trim() || null,
        year: form.year ? Number(form.year) : null,
        status: form.status,
        notes: form.notes.trim() || null,
      };
      return current ? updateVehicle(current.id, body) : createVehicle(schoolId!, body);
    },
    onSuccess: (v) => {
      toast.success(current ? "Vehicle updated" : "Vehicle added. Upload its RC, insurance and PUC next.");
      void qc.invalidateQueries({ queryKey: ["vehicles"] });
      if (!current) setSheet({ open: true, id: v.id });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const set = (k: keyof Form) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  return (
    <DashboardLayout>
      <PlanGateBanner feature="vehicles" />
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Vehicles</h1>
          <p className="text-sm text-muted-foreground mt-1">Fleet status and papers. Only active vehicles can be booked.</p>
        </div>
        <Button disabled={!canWrite} onClick={() => setSheet({ open: true, id: null })} data-testid="button-add-vehicle">
          <Plus className="h-4 w-4 mr-1" /> Add vehicle
        </Button>
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : !data?.length ? (
        <div className="py-16 text-center text-muted-foreground">
          <Car className="h-10 w-10 mx-auto mb-2 opacity-30" />
          No vehicles registered.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((v) => (
            <button key={v.id} type="button" onClick={() => setSheet({ open: true, id: v.id })}
              className="w-full flex items-center justify-between px-5 py-3.5 text-left hover:bg-muted/40" data-testid={`row-vehicle-${v.id}`}>
              <div>
                <div className="font-medium text-sm">{v.registrationNumber}</div>
                <div className="text-xs text-muted-foreground">{[v.type, v.transmission, v.fuelType, v.makeModel, v.year].filter(Boolean).join(" · ")}</div>
              </div>
              <div className="flex items-center gap-2">
                {!!v.expiredDocuments && (
                  <span className="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                    <AlertTriangle className="h-3 w-3" /> {v.expiredDocuments} expired
                  </span>
                )}
                {!!v.expiringDocuments && (
                  <span className="text-xs px-2 py-0.5 rounded-full font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                    {v.expiringDocuments} expiring
                  </span>
                )}
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${STATUS_TONE[v.status]}`}>{v.status}</span>
                <ChevronRight className="h-4 w-4 text-muted-foreground" />
              </div>
            </button>
          ))}
        </div>
      )}

      <Sheet open={sheet.open} onOpenChange={(o) => { if (!o) setSheet({ open: false, id: null }); }}>
        <SheetContent className="w-full sm:max-w-xl overflow-y-auto">
          <SheetHeader>
            <SheetTitle>{current ? current.registrationNumber : "Add vehicle"}</SheetTitle>
            <SheetDescription>Retired vehicles stay in history but can no longer be booked or assigned.</SheetDescription>
          </SheetHeader>
          <fieldset disabled={!canWrite} className="grid gap-3 sm:grid-cols-2 mt-4">
            <div><Label htmlFor="vh-reg">Registration number</Label><Input id="vh-reg" value={form.registrationNumber} onChange={set("registrationNumber")} placeholder="MH12AB1234" /></div>
            <div><Label htmlFor="vh-model">Make & model</Label><Input id="vh-model" value={form.makeModel} onChange={set("makeModel")} placeholder="Maruti Swift" /></div>
            <div><Label htmlFor="vh-type">Type</Label>
              <select id="vh-type" className={SELECT} value={form.type} onChange={set("type")}>
                <option value="car">Car</option><option value="bike">Bike</option><option value="scooter">Scooter</option><option value="heavy">Heavy</option>
              </select></div>
            <div><Label htmlFor="vh-trans">Transmission</Label>
              <select id="vh-trans" className={SELECT} value={form.transmission} onChange={set("transmission")}>
                <option value="">—</option><option value="manual">Manual</option><option value="automatic">Automatic</option>
              </select></div>
            <div><Label htmlFor="vh-fuel">Fuel</Label>
              <select id="vh-fuel" className={SELECT} value={form.fuelType} onChange={set("fuelType")}>
                <option value="">—</option><option value="petrol">Petrol</option><option value="diesel">Diesel</option><option value="cng">CNG</option><option value="electric">Electric</option>
              </select></div>
            <div><Label htmlFor="vh-year">Year</Label><Input id="vh-year" type="number" min={1990} max={2100} value={form.year} onChange={set("year")} /></div>
            <div><Label htmlFor="vh-status">Status</Label>
              <select id="vh-status" className={SELECT} value={form.status} onChange={set("status")}>
                <option value="active">Active</option><option value="maintenance">In maintenance</option><option value="retired">Retired</option>
              </select></div>
            <div className="sm:col-span-2"><Label htmlFor="vh-notes">Notes</Label><Textarea id="vh-notes" rows={2} value={form.notes} onChange={set("notes")} /></div>
          </fieldset>
          {canWrite && (
            <Button className="mt-4" disabled={!form.registrationNumber.trim() || save.isPending} onClick={() => save.mutate()} data-testid="button-save-vehicle">
              {current ? "Save changes" : "Add vehicle"}
            </Button>
          )}
          {current && (
            <div className="mt-8">
              <h3 className="font-semibold mb-1">Papers</h3>
              <p className="text-xs text-muted-foreground mb-3">Status follows the expiry date: flagged 30 days before it lapses.</p>
              <DocumentsPanel kind="vehicle" ownerId={current.id} />
            </div>
          )}
        </SheetContent>
      </Sheet>
    </DashboardLayout>
  );
}
