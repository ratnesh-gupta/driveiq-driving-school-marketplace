import { useEffect, useRef, useState } from "react";
import type { School } from "@/api-client/generated/api.schemas";

const MAPS_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined;

export const hasMapsKey = !!MAPS_KEY;

/* Minimal typing for the parts of the Maps JavaScript API used here. */
type LatLng = { lat: number; lng: number };
type GMap = { fitBounds(b: unknown): void; setCenter(c: LatLng): void; setZoom(z: number): void };
type GMarker = { addListener(evt: string, fn: () => void): void; setMap(m: GMap | null): void };
type GoogleMaps = {
  Map: new (el: HTMLElement, opts: Record<string, unknown>) => GMap;
  Marker: new (opts: Record<string, unknown>) => GMarker;
  LatLngBounds: new () => { extend(p: LatLng): void };
  InfoWindow: new (opts: Record<string, unknown>) => {
    setContent(html: string): void;
    open(opts: { map: GMap; anchor: GMarker }): void;
  };
};

let loader: Promise<GoogleMaps> | null = null;

/** Loads the Maps JavaScript API once, only when a map is actually shown. */
function loadGoogleMaps(): Promise<GoogleMaps> {
  const w = window as unknown as { google?: { maps?: GoogleMaps } };
  if (w.google?.maps) return Promise.resolve(w.google.maps);
  if (!loader) {
    loader = new Promise((resolve, reject) => {
      const script = document.createElement("script");
      script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(MAPS_KEY ?? "")}`;
      script.async = true;
      script.onload = () => (w.google?.maps ? resolve(w.google.maps) : reject(new Error("Google Maps failed to load")));
      script.onerror = () => {
        loader = null;
        reject(new Error("Google Maps failed to load"));
      };
      document.head.appendChild(script);
    });
  }
  return loader;
}

function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]!);
}

/** Search results on a map (DIQ-503). Schools without coordinates are skipped. */
export function SchoolsMap({ schools, origin }: { schools: School[]; origin?: LatLng | null }) {
  const ref = useRef<HTMLDivElement>(null);
  const [error, setError] = useState<string | null>(null);
  const located = schools.filter((s) => s.latitude != null && s.longitude != null);

  useEffect(() => {
    if (!MAPS_KEY || !ref.current) return;
    let markers: GMarker[] = [];
    let cancelled = false;

    loadGoogleMaps()
      .then((maps) => {
        if (cancelled || !ref.current) return;
        const map = new maps.Map(ref.current, { center: origin ?? { lat: 18.5204, lng: 73.8567 }, zoom: 12, mapTypeControl: false, streetViewControl: false });
        const bounds = new maps.LatLngBounds();
        const info = new maps.InfoWindow({});

        markers = located.map((s) => {
          const position = { lat: s.latitude as number, lng: s.longitude as number };
          bounds.extend(position);
          const marker = new maps.Marker({ position, map, title: s.name });
          marker.addListener("click", () => {
            // One shared popup; school name is escaped before going into HTML.
            info.setContent(
              `<div style="font-size:13px"><strong>${escapeHtml(s.name)}</strong><br/>★ ${s.rating.toFixed(1)} · from ₹${s.priceFrom.toLocaleString("en-IN")}<br/><a href="/school/${encodeURIComponent(s.slug)}">View school</a></div>`,
            );
            info.open({ map, anchor: marker });
          });
          return marker;
        });

        if (origin) {
          bounds.extend(origin);
          markers.push(new maps.Marker({ position: origin, map, title: "You are here" }));
        }
        if (located.length > 0) map.fitBounds(bounds);
      })
      .catch((e: Error) => setError(e.message));

    return () => {
      cancelled = true;
      markers.forEach((m) => m.setMap(null));
    };
  }, [located.map((s) => s.id).join(","), origin?.lat, origin?.lng]); // eslint-disable-line react-hooks/exhaustive-deps

  if (!MAPS_KEY) return null;

  return (
    <div className="space-y-2">
      <div ref={ref} className="w-full h-[480px] rounded-xl border bg-muted" role="region" aria-label="Map of driving schools" data-testid="map-schools" />
      {error ? (
        <p className="text-sm text-destructive">{error}</p>
      ) : located.length < schools.length ? (
        <p className="text-xs text-muted-foreground">{schools.length - located.length} school(s) without a map location are not shown.</p>
      ) : null}
    </div>
  );
}
