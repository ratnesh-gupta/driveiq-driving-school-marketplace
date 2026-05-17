import { useState } from "react";
import { motion, AnimatePresence } from "framer-motion";
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
import { useListSchools, useListLocalities } from "@workspace/api-client-react";
import { Search, SlidersHorizontal, X, MapPin, Car } from "lucide-react";
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from "@/components/ui/sheet";

function parseQuery(search: string) {
  const params = new URLSearchParams(search);
  return {
    locality: params.get("locality") || "",
    vehicleType: params.get("vehicleType") || "",
  };
}

export default function SearchPage() {
  const qs = typeof window !== "undefined" ? window.location.search : "";
  const initial = parseQuery(qs);

  const [locality, setLocality] = useState(initial.locality);
  const [vehicleType, setVehicleType] = useState(initial.vehicleType);
  const [transmission, setTransmission] = useState("");
  const [hasPickup, setHasPickup] = useState(false);
  const [womenInstructor, setWomenInstructor] = useState(false);
  const [weekendClasses, setWeekendClasses] = useState(false);
  const [minRating, setMinRating] = useState<number>(0);
  const [maxPrice, setMaxPrice] = useState<number>(10000);
  const [nearMe, setNearMe] = useState(false);
  const [radiusKm, setRadiusKm] = useState<number>(5);
  const [geo, setGeo] = useState<{ lat: number; lng: number } | null>(null);

  const { data: localities } = useListLocalities();

  const ALL = "__all__";

  const params: Record<string, unknown> = {};
  if (locality) params.locality = locality;
  if (vehicleType) params.vehicleType = vehicleType;
  if (transmission) params.transmission = transmission;
  if (hasPickup) params.hasPickup = true;
  if (womenInstructor) params.womenInstructor = true;
  if (weekendClasses) params.weekendClasses = true;
  if (minRating > 0) params.minRating = minRating;
  if (maxPrice < 10000) params.maxPrice = maxPrice;
  if (nearMe && geo) {
    params.nearLat = geo.lat;
    params.nearLng = geo.lng;
    params.radiusKm = radiusKm;
  }

  const { data: schools, isLoading } = useListSchools(params as never);

  const activeFilterCount = [locality, vehicleType, transmission, hasPickup, womenInstructor, weekendClasses, minRating > 0, maxPrice < 10000, nearMe].filter(Boolean).length;

  const clearFilters = () => {
    setLocality(""); setVehicleType(""); setTransmission("");
    setHasPickup(false); setWomenInstructor(false); setWeekendClasses(false);
    setMinRating(0); setMaxPrice(10000);
    setNearMe(false); setGeo(null); setRadiusKm(5);
  };

  const handleUseMyLocation = () => {
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
          <Select value={String(radiusKm)} onValueChange={v => setRadiusKm(Number(v))}>
            <SelectTrigger data-testid="select-filter-radius">
              <SelectValue placeholder="Radius" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="2">2 km</SelectItem>
              <SelectItem value="5">5 km</SelectItem>
              <SelectItem value="10">10 km</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Locality</Label>
        <Select value={locality || ALL} onValueChange={v => setLocality(v === ALL ? "" : v)}>
          <SelectTrigger data-testid="select-filter-locality">
            <SelectValue placeholder="All localities" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>All localities</SelectItem>
            {(localities || []).map(loc => (
              <SelectItem key={loc.id} value={loc.slug}>{loc.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Vehicle Type</Label>
        <Select value={vehicleType || ALL} onValueChange={v => setVehicleType(v === ALL ? "" : v)}>
          <SelectTrigger data-testid="select-filter-vehicle">
            <SelectValue placeholder="All types" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>All types</SelectItem>
            {["Car", "Bike", "Scooter", "Heavy Vehicle"].map(v => (
              <SelectItem key={v} value={v}>{v}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Transmission</Label>
        <Select value={transmission || ALL} onValueChange={v => setTransmission(v === ALL ? "" : v)}>
          <SelectTrigger data-testid="select-filter-transmission">
            <SelectValue placeholder="Any" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={ALL}>Any</SelectItem>
            <SelectItem value="Manual">Manual</SelectItem>
            <SelectItem value="Automatic">Automatic</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Minimum Rating: {minRating > 0 ? `${minRating}+` : "Any"}</Label>
        <Slider
          min={0} max={5} step={0.5}
          value={[minRating]}
          onValueChange={([v]) => setMinRating(v)}
          className="w-full"
          data-testid="slider-min-rating"
        />
      </div>

      <div>
        <Label className="text-sm font-semibold mb-3 block">Max Price: ₹{maxPrice.toLocaleString()}</Label>
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
          <X className="h-4 w-4 mr-2" /> Clear Filters
        </Button>
      )}
    </div>
  );

  return (
    <PublicLayout>
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
            <Select value={vehicleType || ALL} onValueChange={v => setVehicleType(v === ALL ? "" : v)}>
              <SelectTrigger className="w-44" data-testid="select-vehicle-type">
                <Car className="h-4 w-4 mr-2" />
                <SelectValue placeholder="Vehicle type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>All types</SelectItem>
                {["Car", "Bike", "Scooter", "Heavy Vehicle"].map(v => (
                  <SelectItem key={v} value={v}>{v}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={String(radiusKm)} onValueChange={v => setRadiusKm(Number(v))}>
              <SelectTrigger className="w-28" data-testid="select-top-radius">
                <SelectValue placeholder="Radius" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="2">2 km</SelectItem>
                <SelectItem value="5">5 km</SelectItem>
                <SelectItem value="10">10 km</SelectItem>
              </SelectContent>
            </Select>

            <Button
              type="button"
              variant={nearMe ? "default" : "outline"}
              onClick={handleUseMyLocation}
              data-testid="button-top-near-me"
            >
              <MapPin className="h-4 w-4 mr-2" />
              Near me
            </Button>

            {activeFilterCount > 0 && (
              <Badge variant="secondary" className="gap-1">
                {activeFilterCount} filter{activeFilterCount > 1 ? "s" : ""}
                <button onClick={clearFilters}><X className="h-3 w-3" /></button>
              </Badge>
            )}

            {/* Mobile filter trigger */}
            <Sheet>
              <SheetTrigger asChild>
                <Button variant="outline" className="md:hidden gap-2" data-testid="button-mobile-filters">
                  <SlidersHorizontal className="h-4 w-4" /> Filters
                  {activeFilterCount > 0 && <Badge className="h-5 w-5 p-0 flex items-center justify-center text-xs">{activeFilterCount}</Badge>}
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
          {/* Sidebar filters — desktop */}
          <aside className="hidden md:block w-64 flex-shrink-0">
            <div className="sticky top-24 rounded-xl border bg-card p-5">
              <div className="flex items-center justify-between mb-6">
                <h2 className="font-semibold flex items-center gap-2">
                  <SlidersHorizontal className="h-4 w-4" /> Filters
                </h2>
                {activeFilterCount > 0 && (
                  <button onClick={clearFilters} className="text-xs text-muted-foreground hover:text-destructive">Clear all</button>
                )}
              </div>
              <FilterPanel />
            </div>
          </aside>

          {/* Results */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between mb-6">
              <div>
                <h1 className="text-xl font-bold">
                  {locality ? `Schools in ${locality.charAt(0).toUpperCase() + locality.slice(1)}` : "All Driving Schools"}
                </h1>
                {!isLoading && (
                  <p className="text-sm text-muted-foreground mt-0.5">{schools?.length || 0} schools found</p>
                )}
              </div>
            </div>

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
                <p className="text-sm mt-1">Try adjusting your filters</p>
                <Button variant="outline" className="mt-4" onClick={clearFilters}>Clear filters</Button>
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
                      <SchoolCard school={school} />
                    </motion.div>
                  ))}
                </AnimatePresence>
              </motion.div>
            )}
          </div>
        </div>
      </div>
    </PublicLayout>
  );
}
