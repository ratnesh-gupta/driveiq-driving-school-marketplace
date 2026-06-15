import { create } from "zustand";

const STORAGE_KEY = "driveiq-compare";
const MAX_SCHOOLS = 4;

interface ComparisonState {
  schoolIds: number[];
  isBarVisible: boolean;
}

interface ComparisonActions {
  addSchool: (id: number) => void;
  removeSchool: (id: number) => void;
  clearAll: () => void;
  isInComparison: (id: number) => boolean;
  canAdd: () => boolean;
}

function loadFromSession(): number[] {
  try {
    const stored = sessionStorage.getItem(STORAGE_KEY);
    if (stored) return JSON.parse(stored);
  } catch {}
  return [];
}

function persistToSession(ids: number[]) {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
  } catch {}
}

const initialIds = loadFromSession();

export const useComparisonStore = create<ComparisonState & ComparisonActions>((set, get) => ({
  schoolIds: initialIds,
  isBarVisible: initialIds.length >= 2,

  addSchool: (id) => {
    const { schoolIds } = get();
    if (schoolIds.length >= MAX_SCHOOLS || schoolIds.includes(id)) return;
    const next = [...schoolIds, id];
    persistToSession(next);
    set({ schoolIds: next, isBarVisible: next.length >= 2 });
  },

  removeSchool: (id) => {
    const next = get().schoolIds.filter((sid) => sid !== id);
    persistToSession(next);
    set({ schoolIds: next, isBarVisible: next.length >= 2 });
  },

  clearAll: () => {
    sessionStorage.removeItem(STORAGE_KEY);
    set({ schoolIds: [], isBarVisible: false });
  },

  isInComparison: (id) => get().schoolIds.includes(id),

  canAdd: () => get().schoolIds.length < MAX_SCHOOLS,
}));
