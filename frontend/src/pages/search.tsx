import { useEffect, useState, useMemo } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Link } from "wouter";
import { PublicLayout } from "@/components/layout/public-layout";
import { SchoolCard } from "@/components/school-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Checkbox } from "@/components/ui/checkbox";
import { Slider } from "@/components/ui/slider";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { useListLocalities } from "@/api-client";
import type { School } from "@/api-client/generated/api.schemas";
import { useQuery } from "@tanstack/react-query";
import { requestPage } from "@/lib/ops-api";
import { Pager } from "@/components/pager";
import { SchoolsMap, hasMapsKey } from "@/components/maps/schools-map";
import type { ListSchoolsParams } from "@/api-client/generated/api.schemas";
import { Search, SlidersHorizontal, X, MapPin, Car } from "lucide-react";
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from "@/components/ui/sheet";
import { CompareBar } from "@/features/comparison/components/compare-bar";

function parseQuery(search: string) {
  const params = new URLSearchParams(search);
  return {
    locality: params.get("locality") || "",
    vehicleType: params.get("vehicleType") || "",
    wantsNear: params.get("near") === "1",
    listingType: (["school", "trainer"].includes(params.get("type") ?? "") ? params.get("type") : "") as "" | "school" | "trainer",
  };
}

const ALL = "__all__";

// Coordinates stay in this tab's sessionStorage only: never in the URL, localStorage or shared links.
const GEO_KEY = "driveiq_near_geo";
type Geo = { lat: number; lng: number };
function readStoredGeo(): Geo | null {
  try {
    const v = JSON.parse(sessionStorage.getItem(GEO_KEY) ?? "null");
    return v && typeof v.lat === "number" && typeof v.lng === "number" ? v : null;
  } catch {
    return null;
  }
}
function storeGeo(g: Geo | null) {
  try {
    if (g) sessionStorage.setItem(GEO_KEY, JSON.stringify(g));
    else sessionStorage.removeItem(GEO_KEY);
  } catch {
    /* storage blocked: the choice just lasts until reload */
  }
}

export default function SearchPage() {
  const qs = typeof window !== "undefined" ? window.location.search : "";
  const initial = parseQuery(qs);

  const [locality, setLocality] = useState(initial.locality);
  const [vehicleType, setVehicleType] = useState(initial.vehicleType);
  const [transmission, setTransmission] = useState("");
  const [listingType, setListingType] = useState<"" | "school" | "trainer">(initial.listingType);
  const [hasPickup, setHasPickup] = useState(false);
  const [womenInstructor, setWomenInstructor] = useState(false);
  const [weekendClasses, setWeekendClasses] = useState(false);
  const [verifiedOnly, setVerifiedOnly] = useState(false);
  const [minRating, setMinRating] = useState<number>(0);
  const [maxPrice, setMaxPrice] = useState<number>(10000);
  const [storedGeo] = useState(readStoredGeo);
  const [nearMe, setNearMe] = useState(storedGeo !== null);
  const [radiusKm, setRadiusKm] = useState<number>(5);
  const [geo, setGeoState] = useState<Geo | null>(storedGeo);
  const setGeo = (g: Geo | null) => {
    setGeoState(g);
    storeGeo(g);
  };
  const [locationPrompt, setLocationPrompt] = useState(initial.wantsNear && storedGeo === null);
  const [view, setView] = useState<"list" | "map">("list");

  const { data: localities } = useListLocalities();

  const params = useMemo<ListSchoolsParams>(() => {
    const p: ListSchoolsParams = {};
    if (locality) p.locality = locality.toLowerCase();
    if (vehicleType) p.vehicleType = vehicleType.toLowerCase();
    if (transmission) p.transmission = transmission.toLowerCase();
    if (listingType) p.listingType = listingType;
    if (hasPickup) p.hasPickup = true;
    if (womenInstructor) p.womenInstructor = true;
    if (weekendClasses) p.weekendClasses = true;
    if (verifiedOnly) p.verified = true;
    if (minRating > 0) p.minRating = minRating;
    if (maxPrice < 10000) p.maxPrice = maxPrice;
    if (nearMe && geo) {
      p.nearLat = geo.lat;
      p.nearLng = geo.lng;
      p.radiusKm = radiusKm;
    }
    return p;
  }, [locality, vehicleType, transmission, listingType, hasPickup, womenInstructor, weekendClasses, verifiedOnly, minRating, maxPrice, nearMe, geo, radiusKm]);

  // The API pages and ranks in SQL; show the real total and page through it (DIQ-1305, QA BUG-03).
  const PER_PAGE = 24;
  const [page, setPage] = useState(1);
  const paramsKey = JSON.stringify(params);
  useEffect(() => setPage(1), [paramsKey]);
  const { data: result, isLoading } = useQuery({
    queryKey: ["schools-search", params, page],
    queryFn: () => requestPage<School>("/api/schools", {
      ...Object.fromEntries(Object.entries(params).map(([k, v]) => [k, v === true ? 1 : (v as string | number)])),
      limit: PER_PAGE,
      offset: (page - 1) * PER_PAGE,
    }),
    placeholderData: (prev) => prev,
  });
  const schools = result?.items;
  const total = result?.total ?? 0;
  const goToPage = (p: number) => {
    setPage(p);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const activeFilterCount = [
    locality, vehicleType, transmission, listingType, hasPickup, womenInstructor,
    weekendClasses, verifiedOnly, minRating > 0, maxPrice < 10000, nearMe,
  ].filter(Boolean).length;

  const clearFilters = () => {
    setLocality(""); setVehicleType(""); setTransmission(""); setListingType("");
    setHasPickup(false); setWomenInstructor(false); setWeekendClasses(false); setVerifiedOnly(false);
    setMinRating(0); setMaxPrice(10000);
    setNearMe(false); setGeo(null); setRadiusKm(5);
  };

  const requestBrowserLocation = () => {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setGeo({ lat: pos.coords.latitude, lng: pos.coords.longitude });
        setNearMe(true);
      },
      () => {
        setNearMe(false);
        setGeo(null);
      }
    );
  };

  /** Show purpose notice before the browser permission prompt. */
  const handleUseMyLocation = () => {
    if (nearMe && geo) {
      setNearMe(false);
      setGeo(null);
      return;
    }
    setLocationPrompt(true);
  };

  const confirmLocationUse = () => {
    setLocationPrompt(false);
    requestBrowserLocation();
  };

  const FilterPanel = () => (
    <div className="space-y-6">
      <div>
        <Label className="text-sm font-semibold mb-3 block">Nearby Search</Label>
        <div className="space-y-3">
          <Button
            type="button"
            variant={nearMe ? "default" : "outline"}
            className="w-full"
            onClick={handleUseMyLocation}
            data-testid="button-use-my-location"
          >
            <MapPin className="h-4 w-4 mr-2" />
            {nearMe ? "Using your location" : "Use my location"}
          </Button>
          <p className="text-[11px] text-muted-foreground leading-snug">
            Optional. Used only to rank schools near you for this search — not continuous tracking.{" "}
            <Link href="/privacy" className="underline">Privacy</Link>
          </p>
          {nearMe && (
            <Select value={String(radiusKm)} onValueChange={(v) => setRadiusKm(Number(v))}>
              <SelectTrigger data-testid="select-filter-radius">
                <SelectValue placeholder="Radius" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="2">2 km</SelectItem>
                <SelectItem value="5">5 km</SelectItem>
                <SelectItem value="10">10 km</SelectItem>
                <SelectItem value="15">15 km</SelectItem>
                <SelectItem value="20">20 km</SelectItem>
              </SelectContent>
            </Select>
          )}
        </div>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Locality</Label>
        <Select value={locality || ALL} onValueChange={(v) => setLocality(v === ALL ? "" : v)}>
          <SelectTrigger data-testid="select-filter-locality">
            <SelectValue placeholder="All localities" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>All localities</SelectItem>
            {(localities || []).map((loc) => (
              <SelectItem key={loc.id} value={loc.slug}>{loc.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Who teaches</Label>
        <Select value={listingType || ALL} onValueChange={(v) => setListingType(v === ALL ? "" : (v as "school" | "trainer"))}>
          <SelectTrigger data-testid="select-filter-listing-type">
            <SelectValue placeholder="Schools and trainers" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Schools and trainers</SelectItem>
            <SelectItem value="school">Driving schools</SelectItem>
            <SelectItem value="trainer">Independent trainers</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Vehicle Type</Label>
        <Select value={vehicleType || ALL} onValueChange={(v) => setVehicleType(v === ALL ? "" : v)}>
          <SelectTrigger data-testid="select-filter-vehicle">
            <SelectValue placeholder="All types" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>All types</SelectItem>
            {["Car", "Bike", "Scooter", "Heavy Vehicle"].map((v) => (
              <SelectItem key={v} value={v}>{v}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Transmission</Label>
        <Select value={transmission || ALL} onValueChange={(v) => setTransmission(v === ALL ? "" : v)}>
          <SelectTrigger data-testid="select-filter-transmission">
            <SelectValue placeholder="Any" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Any</SelectItem>
            <SelectItem value="manual">Manual</SelectItem>
            <SelectItem value="automatic">Automatic</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">
          Minimum Rating: {minRating > 0 ? `${minRating}+` : "Any"}
        </Label>
        <Slider
          min={0} max={5} step={0.5}
          value={[minRating]}
          onValueChange={([v]) => setMinRating(v)}
          className="w-full"
          data-testid="slider-min-rating"
        />
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">
          Max Price: {maxPrice < 10000 ? `₹${maxPrice.toLocaleString()}` : "Any"}
        </Label>
        <Slider
          min={1000} max={10000} step={500}
          value={[maxPrice]}
          onValueChange={([v]) => setMaxPrice(v)}
          className="w-full"
          data-testid="slider-max-price"
        />
      </div>

      <div className="space-y-3">
        <Label className="text-sm font-semibold block">Features</Label>
        {[
          { label: "Pickup & Drop", value: hasPickup, setter: setHasPickup, id: "pickup" },
          { label: "Women Instructor", value: womenInstructor, setter: setWomenInstructor, id: "women" },
          { label: "Weekend Classes", value: weekendClasses, setter: setWeekendClasses, id: "weekend" },
          { label: "Verified schools only", value: verifiedOnly, setter: setVerifiedOnly, id: "verified" },
        ].map((item) => (
          <div key={item.id} className="flex items-center gap-2">
            <Checkbox
              id={item.id}
              checked={item.value}
              onCheckedChange={(v) => item.setter(!!v)}
              data-testid={`checkbox-${item.id}`}
            />
            <label htmlFor={item.id} className="text-sm cursor-pointer">{item.label}</label>
          </div>
        ))}
      </div>

      {activeFilterCount > 0 && (
        <Button variant="outline" className="w-full" onClick={clearFilters} data-testid="button-clear-filters">
          <X className="h-4 w-4 mr-2" /> Clear All Filters
        </Button>
      )}
    </div>
  );

  return (
    <PublicLayout>
      {locationPrompt && (
        <div className="fixed inset-0 z-[90] flex items-end sm:items-center justify-center bg-black/40 p-4">
          <div
            role="dialog"
            aria-labelledby="location-purpose-title"
            className="w-full max-w-md rounded-2xl border bg-background p-5 shadow-xl space-y-4"
          >
            <h2 id="location-purpose-title" className="text-lg font-semibold">Use your location?</h2>
            <p className="text-sm text-muted-foreground leading-relaxed">
              DriveQ will ask your browser for your current position <strong>only to show driving schools near you</strong> for this search.
              We do not track you continuously or sell location data. You can use locality filters instead.
            </p>
            <p className="text-xs text-muted-foreground">
              Details in our <Link href="/privacy" className="underline text-primary">Privacy Policy</Link>.
            </p>
            <div className="flex flex-wrap gap-2 justify-end">
              <Button variant="ghost" onClick={() => setLocationPrompt(false)}>Not now</Button>
              <Button onClick={confirmLocationUse}>
                <MapPin className="h-4 w-4 mr-1" /> Continue
              </Button>
            </div>
          </div>
        </div>
      )}

      <div className="bg-muted/30 border-b py-6">
        <div className="container mx-auto px-4">
          <div className="flex items-center gap-3 flex-wrap">
            <div className="relative flex-1 min-w-48">
              <MapPin className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder="Search by locality..."
                value={locality}
                onChange={(e) => setLocality(e.target.value)}
                className="pl-9"
                data-testid="input-search-locality"
              />
            </div>
            <Select value={vehicleType || ALL} onValueChange={(v) => setVehicleType(v === ALL ? "" : v)}>
              <SelectTrigger className="w-44" data-testid="select-vehicle-type">
                <Car className="h-4 w-4 mr-2" />
                <SelectValue placeholder="Vehicle type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>All types</SelectItem>
                {["Car", "Bike", "Scooter", "Heavy Vehicle"].map((v) => (
                  <SelectItem key={v} value={v}>{v}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Button
              type="button"
              variant={nearMe ? "default" : "outline"}
              onClick={handleUseMyLocation}
              data-testid="button-top-near-me"
            >
              <MapPin className="h-4 w-4 mr-2" />
              {nearMe ? `Within ${radiusKm}km` : "Near me"}
            </Button>

            {activeFilterCount > 0 && (
              <Badge variant="secondary" className="gap-1">
                {activeFilterCount} filter{activeFilterCount > 1 ? "s" : ""}
                <button onClick={clearFilters}><X className="h-3 w-3" /></button>
              </Badge>
            )}

            <Sheet>
              <SheetTrigger asChild>
                <Button variant="outline" className="md:hidden gap-2" data-testid="button-mobile-filters">
                  <SlidersHorizontal className="h-4 w-4" /> Filters
                  {activeFilterCount > 0 && (
                    <Badge className="h-5 w-5 p-0 flex items-center justify-center text-xs">{activeFilterCount}</Badge>
                  )}
                </Button>
              </SheetTrigger>
              <SheetContent side="left" className="overflow-y-auto">
                <SheetHeader><SheetTitle>Filters</SheetTitle></SheetHeader>
                <div className="mt-6">
                  <FilterPanel />
                </div>
              </SheetContent>
            </Sheet>
          </div>
        </div>
      </div>

      <div className="container mx-auto px-4 py-8">
        <div className="flex gap-8">
          <aside className="hidden md:block w-64 flex-shrink-0">
            <div className="sticky top-24 rounded-xl border bg-card p-5">
              <div className="flex items-center justify-between mb-6">
                <h2 className="font-semibold flex items-center gap-2">
                  <SlidersHorizontal className="h-4 w-4" /> Filters
                </h2>
                {activeFilterCount > 0 && (
                  <button onClick={clearFilters} className="text-xs text-muted-foreground hover:text-destructive">
                    Clear all
                  </button>
                )}
              </div>
              <FilterPanel />
            </div>
          </aside>

          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h1 className="text-xl font-bold">
                  {locality
                    ? `Schools in ${locality.charAt(0).toUpperCase() + locality.slice(1)}`
                    : "All Driving Schools"}
                </h1>
                {!isLoading && (
                  <p className="text-sm text-muted-foreground mt-0.5">
                    {total} {total === 1 ? "school" : "schools"} found
                    {nearMe && geo && ` within ${radiusKm}km`}
                  </p>
                )}
              </div>
              {hasMapsKey && (
                <div className="flex rounded-lg border p-0.5 text-sm" role="group" aria-label="Results view">
                  {(["list", "map"] as const).map((v) => (
                    <button
                      key={v}
                      type="button"
                      aria-pressed={view === v}
                      onClick={() => setView(v)}
                      className={`px-3 py-1 rounded-md capitalize ${view === v ? "bg-primary text-primary-foreground" : "text-muted-foreground"}`}
                      data-testid={`button-view-${v}`}
                    >
                      {v}
                    </button>
                  ))}
                </div>
              )}
            </div>

            {view === "map" && !isLoading && !!schools?.length && (
              <div className="mb-6">
                <SchoolsMap schools={schools} origin={nearMe ? geo : null} />
              </div>
            )}

            {isLoading ? (
              <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-5">
                {Array.from({ length: 6 }).map((_, i) => (
                  <Skeleton key={i} className="h-72 rounded-xl" />
                ))}
              </div>
            ) : !schools?.length ? (
              <div className="text-center py-20 text-muted-foreground">
                <Search className="h-12 w-12 mx-auto mb-4 opacity-20" />
                <p className="text-lg font-medium">No schools found</p>
                <p className="text-sm mt-1">
                  {activeFilterCount > 0
                    ? "Try adjusting your filters or clearing them"
                    : "No schools are available yet"}
                </p>
                {activeFilterCount > 0 && (
                  <Button variant="outline" className="mt-4" onClick={clearFilters}>
                    Clear filters
                  </Button>
                )}
              </div>
            ) : (
              <motion.div
                className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-5"
                initial="initial"
                animate="animate"
                variants={{ animate: { transition: { staggerChildren: 0.05 } } }}
              >
                <AnimatePresence>
                  {schools.map((school) => (
                    <motion.div
                      key={school.id}
                      initial={{ opacity: 0, scale: 0.97 }}
                      animate={{ opacity: 1, scale: 1 }}
                      exit={{ opacity: 0, scale: 0.97 }}
                      transition={{ duration: 0.3 }}
                      data-testid={`card-school-${school.id}`}
                    >
                      <SchoolCard school={school} showCompare />
                    </motion.div>
                  ))}
                </AnimatePresence>
              </motion.div>
            )}
            {!isLoading && total > PER_PAGE && (
              <Pager page={page} perPage={PER_PAGE} total={total} onPage={goToPage} noun="schools" />
            )}
          </div>
        </div>
      </div>
      <CompareBar />
    </PublicLayout>
  );
}
