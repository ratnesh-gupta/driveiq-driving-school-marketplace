import { create } from "zustand";
import { getAreasByCity, getRTOForArea } from "../data";

const STORAGE_KEY = "driveiq-location";

interface LocationState {
  selectedCountry: string;
  selectedState: string;
  selectedCity: string;
  selectedArea: string | null;
  isDetecting: boolean;
}

interface LocationActions {
  setLocation: (state: string, city: string, area?: string | null) => void;
  setArea: (area: string | null) => void;
  detectFromGeolocation: () => Promise<void>;
  clearLocation: () => void;
}

function loadPersistedLocation(): Partial<LocationState> {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) return JSON.parse(stored);
  } catch {}
  return {};
}

function persistLocation(state: LocationState) {
  try {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        selectedCountry: state.selectedCountry,
        selectedState: state.selectedState,
        selectedCity: state.selectedCity,
        selectedArea: state.selectedArea,
      })
    );
  } catch {}
}

const persisted = loadPersistedLocation();

export const useLocationStore = create<LocationState & LocationActions>((set, get) => ({
  selectedCountry: persisted.selectedCountry ?? "IN",
  selectedState: persisted.selectedState ?? "MH",
  selectedCity: persisted.selectedCity ?? "pune",
  selectedArea: persisted.selectedArea ?? null,
  isDetecting: false,

  setLocation: (state, city, area = null) => {
    set({ selectedState: state, selectedCity: city, selectedArea: area });
    persistLocation(get());
  },

  setArea: (area) => {
    set({ selectedArea: area });
    persistLocation(get());
  },

  clearLocation: () => {
    set({ selectedState: "MH", selectedCity: "pune", selectedArea: null });
    localStorage.removeItem(STORAGE_KEY);
  },

  detectFromGeolocation: async () => {
    if (!navigator.geolocation) return;
    set({ isDetecting: true });
    try {
      const position = await new Promise<GeolocationPosition>((resolve, reject) =>
        navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 10000 })
      );
      const { latitude, longitude } = position.coords;
      const { selectedCity, selectedState } = get();
      const areas = getAreasByCity(selectedCity, selectedState);

      let closestArea: string | null = null;
      let minDist = Infinity;
      for (const area of areas) {
        const rto = getRTOForArea(selectedCity, area.slug, selectedState);
        if (!rto?.coordinates) continue;
        const dist = Math.hypot(rto.coordinates.lat - latitude, rto.coordinates.lng - longitude);
        if (dist < minDist) {
          minDist = dist;
          closestArea = area.slug;
        }
      }
      if (closestArea) {
        set({ selectedArea: closestArea });
        persistLocation(get());
      }
    } catch {
      // User denied or timeout — silently fail
    } finally {
      set({ isDetecting: false });
    }
  },
}));
