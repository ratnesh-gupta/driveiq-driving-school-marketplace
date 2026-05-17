import { create } from "zustand";
import { getStoredToken, loginApi, logoutApi, meApi, registerApi, setStoredToken, type AuthUser, type UserRole } from "@/lib/auth-api";

interface AuthState {
  isLoggedIn: boolean;
  userRole: UserRole | null;
  user: AuthUser | null;
  token: string | null;
  isAuthLoading: boolean;
  authError: string | null;
  hydrateAuth: () => Promise<void>;
  login: (payload: { email: string; password: string }) => Promise<void>;
  register: (payload: { name: string; email: string; password: string; role: UserRole }) => Promise<void>;
  logout: () => Promise<void>;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  isLoggedIn: false,
  userRole: null,
  user: null,
  token: null,
  isAuthLoading: false,
  authError: null,

  hydrateAuth: async () => {
    const token = getStoredToken();
    if (!token) return;

    set({ isAuthLoading: true, authError: null });
    try {
      const user = await meApi(token);
      set({
        token,
        user,
        userRole: user.role,
        isLoggedIn: true,
        isAuthLoading: false,
      });
    } catch {
      setStoredToken(null);
      set({
        token: null,
        user: null,
        userRole: null,
        isLoggedIn: false,
        isAuthLoading: false,
      });
    }
  },

  login: async ({ email, password }) => {
    set({ isAuthLoading: true, authError: null });
    try {
      const data = await loginApi({ email, password });
      setStoredToken(data.token);
      set({
        token: data.token,
        user: data.user,
        userRole: data.user.role,
        isLoggedIn: true,
        isAuthLoading: false,
      });
    } catch (err) {
      set({
        isAuthLoading: false,
        authError: err instanceof Error ? err.message : "Login failed",
      });
      throw err;
    }
  },

  register: async ({ name, email, password, role }) => {
    set({ isAuthLoading: true, authError: null });
    try {
      const data = await registerApi({ name, email, password, role });
      setStoredToken(data.token);
      set({
        token: data.token,
        user: data.user,
        userRole: data.user.role,
        isLoggedIn: true,
        isAuthLoading: false,
      });
    } catch (err) {
      set({
        isAuthLoading: false,
        authError: err instanceof Error ? err.message : "Registration failed",
      });
      throw err;
    }
  },

  logout: async () => {
    const token = get().token;
    if (token) {
      try {
        await logoutApi(token);
      } catch {
        // no-op; still clear local session
      }
    }

    setStoredToken(null);
    set({
      token: null,
      user: null,
      userRole: null,
      isLoggedIn: false,
      authError: null,
      isAuthLoading: false,
    });
  },
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
