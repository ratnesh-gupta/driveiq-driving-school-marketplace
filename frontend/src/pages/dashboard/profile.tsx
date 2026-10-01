import { useState, useEffect, useMemo } from "react";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useToast } from "@/hooks/use-toast";
import { useGetSchool, useUpdateSchool, getGetSchoolQueryKey, useListLocalities } from "@/api-client";
import { useQueryClient } from "@tanstack/react-query";
import { useSchoolId } from "@/hooks/use-school-id";
import { Save, ShieldCheck, X, Plus, LocateFixed } from "lucide-react";
import { ProfileCompleteness } from "@/features/comparison/components/profile-completeness";

interface BatchTiming {
  slot: string;
  time: string;
  label?: string;
  enabled: boolean;
}

const DEFAULT_BATCH_TIMINGS: BatchTiming[] = [
  { slot: "morning", time: "6am-10am", enabled: false },
  { slot: "afternoon", time: "12pm-4pm", enabled: false },
  { slot: "evening", time: "4pm-8pm", enabled: false },
];

interface FormState {
  name: string;
  phone: string;
  whatsapp: string;
  email: string;
  description: string;
  timings: string;
  hasPickup: boolean;
  womenInstructor: boolean;
  weekendClasses: boolean;
  simulatorTraining: boolean;
  acVehicle: boolean;
  rtoAssistance: boolean;
  languages: string[];
  batchTimings: BatchTiming[];
  acceptedPayments: string[];
  establishedYear: string;
  totalVehicles: string;
  totalInstructors: string;
  cancellationPolicy: string;
  pickupRadiusKm: string;
  address: string;
  vehicleTypes: string[];
  transmission: string[];
  serviceAreas: string[];
  priceFrom: string;
  localityId: string;
  latitude: string;
  longitude: string;
}

const VEHICLE_TYPES = [
  { value: "car", label: "Car" },
  { value: "bike", label: "Bike" },
  { value: "scooter", label: "Scooter" },
  { value: "heavy", label: "Heavy vehicle" },
];
const TRANSMISSIONS = [
  { value: "manual", label: "Manual" },
  { value: "automatic", label: "Automatic" },
];

/** Toggle chip for multi-select lists (vehicle types, transmission). */
function Chip({ on, onClick, children }: { on: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      aria-pressed={on}
      onClick={onClick}
      className={`px-3 py-1.5 rounded-full border text-sm transition-colors ${on ? "bg-primary text-primary-foreground border-primary" : "bg-background hover:bg-muted"}`}
    >
      {children}
    </button>
  );
}

export default function ProfilePage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const schoolId = useSchoolId();
  const { data: school, isLoading } = useGetSchool(schoolId!, {
    query: { queryKey: getGetSchoolQueryKey(schoolId!), enabled: !!schoolId },
  });
  const updateSchool = useUpdateSchool();

  const [form, setForm] = useState<FormState>({
    name: "", phone: "", whatsapp: "", email: "", description: "", timings: "",
    hasPickup: false, womenInstructor: false, weekendClasses: false,
    simulatorTraining: false, acVehicle: false, rtoAssistance: true,
    languages: [], batchTimings: DEFAULT_BATCH_TIMINGS, acceptedPayments: [],
    establishedYear: "", totalVehicles: "", totalInstructors: "",
    cancellationPolicy: "", pickupRadiusKm: "",
    address: "", vehicleTypes: [], transmission: [], serviceAreas: [], priceFrom: "",
    localityId: "", latitude: "", longitude: "",
  });
  const { data: localities } = useListLocalities();
  const [locating, setLocating] = useState(false);
  const isTrainer = school?.listingType === "trainer";

  /** The owner fills in their own business location; nothing is tracked. */
  const fillCurrentLocation = () => {
    if (!navigator.geolocation) {
      toast({ title: "Your browser cannot share a location. Enter the coordinates instead.", variant: "destructive" });
      return;
    }
    setLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLocating(false);
        setForm((p) => ({ ...p, latitude: pos.coords.latitude.toFixed(6), longitude: pos.coords.longitude.toFixed(6) }));
      },
      () => {
        setLocating(false);
        toast({ title: "Location not shared. Enter the coordinates from Google Maps instead.", variant: "destructive" });
      },
      { timeout: 10000 },
    );
  };
  const [newArea, setNewArea] = useState("");

  const [newLanguage, setNewLanguage] = useState("");
  const [newPayment, setNewPayment] = useState("");
  const [showCustomTiming, setShowCustomTiming] = useState(false);
  const [customTimingLabel, setCustomTimingLabel] = useState("");
  const [customTimingTime, setCustomTimingTime] = useState("");

  useEffect(() => {
    if (school) {
      const schoolBatchTimings = school.batchTimings?.length
        ? school.batchTimings
        : DEFAULT_BATCH_TIMINGS;

      setForm({
        name: school.name || "",
        phone: school.phone || "",
        whatsapp: school.whatsapp || "",
        email: school.email || "",
        description: school.description || "",
        timings: school.timings || "",
        hasPickup: school.hasPickup,
        womenInstructor: school.womenInstructor,
        weekendClasses: school.weekendClasses,
        simulatorTraining: school.simulatorTraining,
        acVehicle: school.acVehicle,
        rtoAssistance: school.rtoAssistance,
        languages: school.languages || [],
        batchTimings: schoolBatchTimings,
        acceptedPayments: school.acceptedPayments || [],
        establishedYear: school.establishedYear || "",
        totalVehicles: school.totalVehicles != null ? String(school.totalVehicles) : "",
        totalInstructors: school.totalInstructors != null ? String(school.totalInstructors) : "",
        cancellationPolicy: school.cancellationPolicy || "",
        pickupRadiusKm: school.pickupRadiusKm != null ? String(school.pickupRadiusKm) : "",
        address: school.address || "",
        vehicleTypes: school.vehicleTypes || [],
        transmission: school.transmission || [],
        serviceAreas: school.serviceAreas || [],
        priceFrom: school.priceFrom ? String(school.priceFrom) : "",
        localityId: school.localityId ? String(school.localityId) : "",
        latitude: school.latitude != null ? String(school.latitude) : "",
        longitude: school.longitude != null ? String(school.longitude) : "",
      });
    }
  }, [school]);

  const missingFields = useMemo(() => {
    const missing: string[] = [];
    if (!form.languages.length) missing.push("languages");
    if (!form.batchTimings.some((b) => b.enabled)) missing.push("batch timings");
    if (!form.acceptedPayments.length) missing.push("payment methods");
    if (!form.establishedYear) missing.push("established year");
    if (!form.totalVehicles) missing.push("total vehicles");
    if (!form.totalInstructors) missing.push("total instructors");
    if (!form.description) missing.push("description");
    if (!form.vehicleTypes.length) missing.push("vehicle types");
    if (!form.transmission.length) missing.push("transmission");
    if (!form.serviceAreas.length) missing.push("service areas");
    if (!form.address) missing.push("address");
    return missing;
  }, [form]);

  const handleSave = () => {
    const data: Record<string, unknown> = {
      name: form.name,
      phone: form.phone,
      whatsapp: form.whatsapp,
      email: form.email,
      description: form.description,
      timings: form.timings,
      hasPickup: form.hasPickup,
      womenInstructor: form.womenInstructor,
      weekendClasses: form.weekendClasses,
      simulatorTraining: form.simulatorTraining,
      acVehicle: form.acVehicle,
      rtoAssistance: form.rtoAssistance,
      languages: form.languages,
      batchTimings: form.batchTimings,
      acceptedPayments: form.acceptedPayments,
      establishedYear: form.establishedYear || null,
      totalVehicles: form.totalVehicles ? Number(form.totalVehicles) : null,
      totalInstructors: form.totalInstructors ? Number(form.totalInstructors) : null,
      cancellationPolicy: form.cancellationPolicy || null,
      pickupRadiusKm: form.pickupRadiusKm ? Number(form.pickupRadiusKm) : null,
      ...(form.address.trim() ? { address: form.address.trim() } : {}),
      vehicleTypes: form.vehicleTypes,
      transmission: form.transmission,
      serviceAreas: form.serviceAreas,
      ...(form.priceFrom ? { priceFrom: Number(form.priceFrom) } : {}),
      ...(form.localityId ? { localityId: Number(form.localityId) } : {}),
      ...(form.latitude && form.longitude ? { latitude: Number(form.latitude), longitude: Number(form.longitude) } : {}),
    };

    updateSchool.mutate(
      { id: schoolId!, data: data as any },
      {
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: getGetSchoolQueryKey(schoolId!) });
          toast({ title: "Profile updated!" });
        },
        onError: () => toast({ title: "Update failed", variant: "destructive" }),
      }
    );
  };

  const toggleIn = (field: "vehicleTypes" | "transmission", value: string) =>
    setForm((p) => ({
      ...p,
      [field]: p[field].includes(value) ? p[field].filter((v) => v !== value) : [...p[field], value],
    }));

  const addTag = (
    field: "languages" | "acceptedPayments" | "serviceAreas",
    value: string,
    setter: (v: string) => void
  ) => {
    const trimmed = value.trim();
    if (!trimmed || form[field].includes(trimmed)) return;
    setForm((p) => ({ ...p, [field]: [...p[field], trimmed] }));
    setter("");
  };

  const removeTag = (field: "languages" | "acceptedPayments" | "serviceAreas", value: string) => {
    setForm((p) => ({ ...p, [field]: p[field].filter((t) => t !== value) }));
  };

  const toggleBatchTiming = (index: number) => {
    setForm((p) => ({
      ...p,
      batchTimings: p.batchTimings.map((bt, i) =>
        i === index ? { ...bt, enabled: !bt.enabled } : bt
      ),
    }));
  };

  const addCustomTiming = () => {
    if (!customTimingLabel.trim() || !customTimingTime.trim()) return;
    setForm((p) => ({
      ...p,
      batchTimings: [
        ...p.batchTimings,
        { slot: "custom", label: customTimingLabel.trim(), time: customTimingTime.trim(), enabled: true },
      ],
    }));
    setCustomTimingLabel("");
    setCustomTimingTime("");
    setShowCustomTiming(false);
  };

  const removeCustomTiming = (index: number) => {
    setForm((p) => ({
      ...p,
      batchTimings: p.batchTimings.filter((_, i) => i !== index),
    }));
  };

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">{isTrainer ? "Trainer Profile" : "School Profile"}</h1>
        <p className="text-muted-foreground text-sm mt-1">
          {isTrainer ? "Manage your public trainer profile" : "Manage your school's public profile"}
        </p>
      </div>

      {isLoading ? (
        <div className="space-y-4 max-w-3xl">
          <Skeleton className="h-12" />
          <Skeleton className="h-12" />
          <Skeleton className="h-32" />
        </div>
      ) : (
        <div className="max-w-3xl space-y-6">
          {school?.verified && (
            <div className="flex items-center gap-2 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 text-sm">
              <ShieldCheck className="h-4 w-4" />
              Your school is verified on DriveIQ
            </div>
          )}

          <ProfileCompleteness
            completeness={school?.profileCompleteness ?? 0}
            missingFields={missingFields}
          />

          <Tabs defaultValue="basic" className="space-y-6">
            <TabsList className="w-full justify-start overflow-x-auto">
              <TabsTrigger value="basic">Basic Info</TabsTrigger>
              <TabsTrigger value="features">Features & Services</TabsTrigger>
              <TabsTrigger value="about">About & Policies</TabsTrigger>
            </TabsList>

            {/* Tab 1: Basic Info */}
            <TabsContent value="basic">
              <div className="rounded-xl border bg-card p-6 space-y-5">
                <h2 className="font-semibold text-lg">Basic Information</h2>
                <div>
                  <Label>{isTrainer ? "Your name (as learners see it)" : "School Name"}</Label>
                  <Input value={form.name} onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))} data-testid="input-school-name" />
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <Label>Phone</Label>
                    <Input value={form.phone} onChange={(e) => setForm((p) => ({ ...p, phone: e.target.value }))} data-testid="input-school-phone" />
                  </div>
                  <div>
                    <Label>WhatsApp</Label>
                    <Input value={form.whatsapp} onChange={(e) => setForm((p) => ({ ...p, whatsapp: e.target.value }))} data-testid="input-school-whatsapp" />
                  </div>
                </div>
                <div>
                  <Label>Email</Label>
                  <Input type="email" value={form.email} onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))} data-testid="input-school-email" />
                </div>
                <div>
                  <Label>Description</Label>
                  <Textarea value={form.description} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} rows={4} data-testid="textarea-school-description" />
                </div>
                <div>
                  <Label>Timings</Label>
                  <Input value={form.timings} onChange={(e) => setForm((p) => ({ ...p, timings: e.target.value }))} placeholder="e.g. Mon–Sat 7am–7pm" data-testid="input-school-timings" />
                </div>
                <div>
                  <Label htmlFor="school-address">Address</Label>
                  <Input id="school-address" value={form.address} onChange={(e) => setForm((p) => ({ ...p, address: e.target.value }))} data-testid="input-school-address" />
                </div>
                <div>
                  <Label htmlFor="school-locality">Locality</Label>
                  <select
                    id="school-locality"
                    className="w-full h-9 rounded-md border bg-background px-2 text-sm"
                    value={form.localityId}
                    onChange={(e) => setForm((p) => ({ ...p, localityId: e.target.value }))}
                    data-testid="select-school-locality"
                  >
                    <option value="">Choose your locality</option>
                    {(localities ?? []).map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
                  </select>
                </div>
                <div className="space-y-2">
                  <Label>Location on the map</Label>
                  <p className="text-xs text-muted-foreground">
                    Learners searching near them find you by this point. Stand at your office and use your current
                    location, or copy the coordinates from Google Maps (right-click the place).
                  </p>
                  <div className="flex flex-wrap items-center gap-2">
                    <Input aria-label="Latitude" className="w-36" placeholder="18.5590" value={form.latitude} onChange={(e) => setForm((p) => ({ ...p, latitude: e.target.value }))} data-testid="input-school-latitude" />
                    <Input aria-label="Longitude" className="w-36" placeholder="73.7868" value={form.longitude} onChange={(e) => setForm((p) => ({ ...p, longitude: e.target.value }))} data-testid="input-school-longitude" />
                    <Button type="button" variant="outline" size="sm" onClick={fillCurrentLocation} disabled={locating} data-testid="button-use-current-location">
                      <LocateFixed className="h-4 w-4 mr-1" /> {locating ? "Locating…" : "Use my current location"}
                    </Button>
                  </div>
                </div>
              </div>

              <div className="rounded-xl border bg-card p-6 space-y-5 mt-6" data-testid="section-training-offered">
                <h2 className="font-semibold text-lg">Training offered</h2>
                <div className="space-y-2">
                  <Label>Vehicle types</Label>
                  <div className="flex flex-wrap gap-2">
                    {VEHICLE_TYPES.map((v) => (
                      <Chip key={v.value} on={form.vehicleTypes.includes(v.value)} onClick={() => toggleIn("vehicleTypes", v.value)}>{v.label}</Chip>
                    ))}
                  </div>
                </div>
                <div className="space-y-2">
                  <Label>Transmission</Label>
                  <div className="flex flex-wrap gap-2">
                    {TRANSMISSIONS.map((v) => (
                      <Chip key={v.value} on={form.transmission.includes(v.value)} onClick={() => toggleIn("transmission", v.value)}>{v.label}</Chip>
                    ))}
                  </div>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="price-from">Courses start from (₹)</Label>
                  <Input id="price-from" type="number" min={0} className="w-40" value={form.priceFrom} onChange={(e) => setForm((p) => ({ ...p, priceFrom: e.target.value }))} />
                </div>
                <div className="space-y-2">
                  <Label>Service areas</Label>
                  <p className="text-xs text-muted-foreground">Localities you train in or pick up from. These help learners nearby find you.</p>
                  <div className="flex flex-wrap gap-2">
                    {form.serviceAreas.map((a) => (
                      <Badge key={a} variant="secondary" className="gap-1 pr-1">
                        {a}
                        <button onClick={() => removeTag("serviceAreas", a)} className="ml-1 rounded-full p-0.5 hover:bg-muted" aria-label={`Remove ${a}`}>
                          <X className="h-3 w-3" />
                        </button>
                      </Badge>
                    ))}
                  </div>
                  <div className="flex gap-2">
                    <Input
                      value={newArea}
                      onChange={(e) => setNewArea(e.target.value)}
                      placeholder="e.g. Baner"
                      className="max-w-[200px]"
                      onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), addTag("serviceAreas", newArea, setNewArea))}
                      data-testid="input-service-area"
                    />
                    <Button size="sm" variant="outline" onClick={() => addTag("serviceAreas", newArea, setNewArea)} aria-label="Add service area">
                      <Plus className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>
              </div>
            </TabsContent>

            {/* Tab 2: Features & Services */}
            <TabsContent value="features">
              <div className="space-y-6">
                <div className="rounded-xl border bg-card p-6 space-y-4">
                  <h2 className="font-semibold text-lg">Features</h2>
                  {[
                    { label: "Pickup & Drop Service", key: "hasPickup" as const },
                    { label: "Women Instructor Available", key: "womenInstructor" as const },
                    { label: "Weekend Classes", key: "weekendClasses" as const },
                    { label: "Simulator Training", key: "simulatorTraining" as const },
                    { label: "AC Vehicle", key: "acVehicle" as const },
                    { label: "RTO Assistance", key: "rtoAssistance" as const },
                  ].map((item) => (
                    <div key={item.key} className="flex items-center justify-between py-1">
                      <Label htmlFor={item.key} className="font-normal cursor-pointer">{item.label}</Label>
                      <Switch
                        id={item.key}
                        checked={form[item.key] as boolean}
                        onCheckedChange={(v) => setForm((p) => ({ ...p, [item.key]: v }))}
                        data-testid={`switch-${item.key}`}
                      />
                    </div>
                  ))}
                  {form.hasPickup && (
                    <div className="pt-2">
                      <Label>Pickup Radius (km)</Label>
                      <Input
                        type="number"
                        value={form.pickupRadiusKm}
                        onChange={(e) => setForm((p) => ({ ...p, pickupRadiusKm: e.target.value }))}
                        placeholder="e.g. 5"
                        className="w-32"
                      />
                    </div>
                  )}
                </div>

                {/* Languages */}
                <div className="rounded-xl border bg-card p-6 space-y-3">
                  <h2 className="font-semibold text-lg">Languages Spoken</h2>
                  <div className="flex flex-wrap gap-2">
                    {form.languages.map((lang) => (
                      <Badge key={lang} variant="secondary" className="gap-1 pr-1">
                        {lang}
                        <button onClick={() => removeTag("languages", lang)} className="ml-1 rounded-full p-0.5 hover:bg-muted">
                          <X className="h-3 w-3" />
                        </button>
                      </Badge>
                    ))}
                  </div>
                  <div className="flex gap-2">
                    <Input
                      value={newLanguage}
                      onChange={(e) => setNewLanguage(e.target.value)}
                      placeholder="Add language (e.g. Hindi)"
                      className="max-w-[200px]"
                      onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), addTag("languages", newLanguage, setNewLanguage))}
                    />
                    <Button size="sm" variant="outline" onClick={() => addTag("languages", newLanguage, setNewLanguage)}>
                      <Plus className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>

                {/* Batch Timings */}
                <div className="rounded-xl border bg-card p-6 space-y-4">
                  <h2 className="font-semibold text-lg">Batch Timings</h2>
                  {form.batchTimings.map((bt, i) => (
                    <div key={`${bt.slot}-${i}`} className="flex items-center justify-between py-1">
                      <div>
                        <Label className="font-normal cursor-pointer capitalize">
                          {bt.label || bt.slot}
                        </Label>
                        <span className="text-xs text-muted-foreground ml-2">({bt.time})</span>
                      </div>
                      <div className="flex items-center gap-2">
                        {bt.slot === "custom" && (
                          <button onClick={() => removeCustomTiming(i)} className="text-muted-foreground hover:text-destructive">
                            <X className="h-4 w-4" />
                          </button>
                        )}
                        <Switch checked={bt.enabled} onCheckedChange={() => toggleBatchTiming(i)} />
                      </div>
                    </div>
                  ))}
                  {!showCustomTiming ? (
                    <Button size="sm" variant="outline" onClick={() => setShowCustomTiming(true)} className="gap-1.5">
                      <Plus className="h-3.5 w-3.5" /> Add custom timing
                    </Button>
                  ) : (
                    <div className="flex gap-2 items-end">
                      <div>
                        <Label className="text-xs">Label</Label>
                        <Input value={customTimingLabel} onChange={(e) => setCustomTimingLabel(e.target.value)} placeholder="e.g. Early Bird" className="w-[140px]" />
                      </div>
                      <div>
                        <Label className="text-xs">Time</Label>
                        <Input value={customTimingTime} onChange={(e) => setCustomTimingTime(e.target.value)} placeholder="e.g. 5:30am-7am" className="w-[140px]" />
                      </div>
                      <Button size="sm" onClick={addCustomTiming}>Add</Button>
                      <Button size="sm" variant="ghost" onClick={() => setShowCustomTiming(false)}>Cancel</Button>
                    </div>
                  )}
                </div>

                {/* Accepted Payments */}
                <div className="rounded-xl border bg-card p-6 space-y-3">
                  <h2 className="font-semibold text-lg">Accepted Payments</h2>
                  <div className="flex flex-wrap gap-2">
                    {form.acceptedPayments.map((pm) => (
                      <Badge key={pm} variant="secondary" className="gap-1 pr-1">
                        {pm}
                        <button onClick={() => removeTag("acceptedPayments", pm)} className="ml-1 rounded-full p-0.5 hover:bg-muted">
                          <X className="h-3 w-3" />
                        </button>
                      </Badge>
                    ))}
                  </div>
                  <div className="flex gap-2">
                    <Input
                      value={newPayment}
                      onChange={(e) => setNewPayment(e.target.value)}
                      placeholder="Add payment method (e.g. UPI)"
                      className="max-w-[220px]"
                      onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), addTag("acceptedPayments", newPayment, setNewPayment))}
                    />
                    <Button size="sm" variant="outline" onClick={() => addTag("acceptedPayments", newPayment, setNewPayment)}>
                      <Plus className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                </div>
              </div>
            </TabsContent>

            {/* Tab 3: About & Policies */}
            <TabsContent value="about">
              <div className="rounded-xl border bg-card p-6 space-y-5">
                <h2 className="font-semibold text-lg">About Your School</h2>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <div>
                    <Label>Established Year</Label>
                    <Input
                      value={form.establishedYear}
                      onChange={(e) => setForm((p) => ({ ...p, establishedYear: e.target.value }))}
                      placeholder="e.g. 2015"
                      maxLength={4}
                    />
                  </div>
                  <div>
                    <Label>Total Vehicles</Label>
                    <Input
                      type="number"
                      value={form.totalVehicles}
                      onChange={(e) => setForm((p) => ({ ...p, totalVehicles: e.target.value }))}
                      placeholder="e.g. 8"
                    />
                  </div>
                  <div>
                    <Label>Total Instructors</Label>
                    <Input
                      type="number"
                      value={form.totalInstructors}
                      onChange={(e) => setForm((p) => ({ ...p, totalInstructors: e.target.value }))}
                      placeholder="e.g. 5"
                    />
                  </div>
                </div>
                <div>
                  <Label>Cancellation Policy</Label>
                  <Textarea
                    value={form.cancellationPolicy}
                    onChange={(e) => setForm((p) => ({ ...p, cancellationPolicy: e.target.value }))}
                    rows={3}
                    placeholder="Describe your refund/cancellation policy..."
                  />
                </div>
              </div>
            </TabsContent>
          </Tabs>

          <Button onClick={handleSave} disabled={updateSchool.isPending} className="gap-2" data-testid="button-save-profile">
            <Save className="h-4 w-4" />
            {updateSchool.isPending ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      )}
    </DashboardLayout>
  );
}
