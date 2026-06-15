import { motion } from "framer-motion";
import { MapPin, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { useLocationStore } from "../stores/location-store";
import { getCitiesByState, getAreasByCity } from "../data";
import { cn } from "@/lib/utils";

interface LocationSelectorProps {
  compact?: boolean;
  className?: string;
}

export function LocationSelector({ compact = false, className }: LocationSelectorProps) {
  const {
    selectedState,
    selectedCity,
    selectedArea,
    isDetecting,
    setLocation,
    setArea,
    detectFromGeolocation,
  } = useLocationStore();

  const cities = getCitiesByState(selectedState);
  const areas = getAreasByCity(selectedCity, selectedState);

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.4 }}
      className={cn(
        "flex items-center gap-3",
        compact ? "flex-wrap" : "flex-col sm:flex-row p-4 rounded-xl border bg-card",
        className
      )}
    >
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <MapPin className="h-4 w-4 text-primary" />
        <span className="font-medium">Location:</span>
      </div>

      <Select value="MH" disabled>
        <SelectTrigger className={cn(compact ? "w-[140px]" : "w-[180px]", "h-9")}>
          <SelectValue placeholder="State" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="MH">Maharashtra</SelectItem>
        </SelectContent>
      </Select>

      <Select
        value={selectedCity}
        onValueChange={(city) => setLocation(selectedState, city)}
      >
        <SelectTrigger className={cn(compact ? "w-[120px]" : "w-[160px]", "h-9")}>
          <SelectValue placeholder="City" />
        </SelectTrigger>
        <SelectContent>
          {cities.map((city) => (
            <SelectItem key={city.slug} value={city.slug}>
              {city.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Select
        value={selectedArea ?? "all"}
        onValueChange={(val) => setArea(val === "all" ? null : val)}
      >
        <SelectTrigger className={cn(compact ? "w-[140px]" : "w-[180px]", "h-9")}>
          <SelectValue placeholder="Select area" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All Areas</SelectItem>
          {areas.map((area) => (
            <SelectItem key={area.slug} value={area.slug}>
              {area.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      <Button
        variant="outline"
        size="sm"
        onClick={detectFromGeolocation}
        disabled={isDetecting}
        className="gap-1.5"
      >
        {isDetecting ? (
          <Loader2 className="h-3.5 w-3.5 animate-spin" />
        ) : (
          <MapPin className="h-3.5 w-3.5" />
        )}
        <span className="hidden sm:inline">Use my location</span>
      </Button>
    </motion.div>
  );
}
