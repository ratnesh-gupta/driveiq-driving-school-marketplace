import { useState } from "react";
import { Button } from "@/components/ui/button";
import { MapPin, ExternalLink } from "lucide-react";

const MAPS_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined;

export function googleMapsLink(lat?: number | null, lng?: number | null, fallbackQuery?: string): string {
  const query = lat != null && lng != null ? `${lat},${lng}` : fallbackQuery ?? "";
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}

/**
 * School location (DIQ-503). The embedded Google map loads only when the
 * visitor asks for it: it sends data to Google (DPDP) and would slow the
 * first paint. Without VITE_GOOGLE_MAPS_API_KEY only the link is shown.
 */
export function SchoolMap({
  name,
  address,
  latitude,
  longitude,
}: {
  name: string;
  address?: string | null;
  latitude?: number | null;
  longitude?: number | null;
}) {
  const [showMap, setShowMap] = useState(false);
  const hasCoords = latitude != null && longitude != null;
  const query = hasCoords ? `${latitude},${longitude}` : [name, address].filter(Boolean).join(", ");

  return (
    <div className="space-y-3">
      {MAPS_KEY && showMap ? (
        <iframe
          title={`Map showing ${name}`}
          className="w-full aspect-video rounded-lg border-0"
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
          src={`https://www.google.com/maps/embed/v1/place?key=${encodeURIComponent(MAPS_KEY)}&q=${encodeURIComponent(query)}`}
        />
      ) : MAPS_KEY ? (
        <button
          type="button"
          onClick={() => setShowMap(true)}
          className="w-full aspect-video rounded-lg border bg-muted/40 flex flex-col items-center justify-center gap-2 text-sm text-muted-foreground hover:bg-muted/60 transition-colors"
          data-testid="button-show-map"
        >
          <MapPin className="h-6 w-6" />
          Show map
          <span className="text-[11px]">Loads Google Maps</span>
        </button>
      ) : null}
      <Button asChild variant="outline" size="sm" className="w-full">
        <a href={googleMapsLink(latitude, longitude, query)} target="_blank" rel="noopener noreferrer" data-testid="link-open-maps">
          <ExternalLink className="h-3.5 w-3.5 mr-1.5" /> Open in Google Maps
        </a>
      </Button>
    </div>
  );
}
