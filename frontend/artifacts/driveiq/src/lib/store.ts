import { create } from "zustand";

type UserRole = "user" | "school" | "admin";

interface AuthState {
  isLoggedIn: boolean;
  userRole: UserRole | null;
  login: (role: UserRole) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  isLoggedIn: false,
  userRole: null,
  login: (role) => set({ isLoggedIn: true, userRole: role }),
  logout: () => set({ isLoggedIn: false, userRole: null }),
}));

interface ThemeState {
  theme: "light" | "dark" | "system";
  setTheme: (theme: "light" | "dark" | "system") => void;
}

export const useThemeStore = create<ThemeState>((set) => ({
  theme: (localStorage.getItem("vite-ui-theme") as "light" | "dark" | "system") || "system",
  setTheme: (theme) => {
    localStorage.setItem("vite-ui-theme", theme);
    set({ theme });
  },
}));

interface SearchFilters {
  locality?: string;
  vehicleType?: string;
  transmission?: string;
  minRating?: number;
  maxPrice?: number;
  hasPickup?: boolean;
  womenInstructor?: boolean;
  weekendClasses?: boolean;
  setFilters: (filters: Partial<SearchFilters>) => void;
  clearFilters: () => void;
}

export const useFilterStore = create<SearchFilters>((set) => ({
  setFilters: (filters) => set((state) => ({ ...state, ...filters })),
  clearFilters: () => set({
    locality: undefined,
    vehicleType: undefined,
    transmission: undefined,
    minRating: undefined,
    maxPrice: undefined,
    hasPickup: undefined,
    womenInstructor: undefined,
    weekendClasses: undefined
  }),
}));
