import { create } from "zustand";
import { clearSessionData } from "@/lib/session";
import { ApiValidationError, getStoredToken, loginApi, logoutAllApi, logoutApi, meApi, registerApi, setSessionEndHandler, setStoredToken, type AuthUser, type RegisterPayload, type FieldErrors, type SchoolRole, type UserRole } from "@/lib/auth-api";

interface AuthState {
  isLoggedIn: boolean;
  userRole: UserRole | null;
  user: AuthUser | null;
  token: string | null;
  schoolId: number | null;
  schoolRole: SchoolRole | null;
  isAuthLoading: boolean;
  authError: string | null;
  fieldErrors: FieldErrors;
  hydrateAuth: () => Promise<void>;
  login: (payload: { email: string; password: string }) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  /** Revokes all tokens server-side, then clears this session. Throws (and stays signed in) if the call fails. */
  logoutAll: () => Promise<void>;
  clearAuthErrors: () => void;
}

export const useAuthStore = create<AuthState>((set, get) => ({
  isLoggedIn: false,
  userRole: null,
  user: null,
  token: null,
  schoolId: null,
  schoolRole: null,
  isAuthLoading: !!getStoredToken(),
  authError: null,
  fieldErrors: {},

  clearAuthErrors: () => set({ authError: null, fieldErrors: {} }),

  hydrateAuth: async () => {
    const token = getStoredToken();
    if (!token) return;

    set({ isAuthLoading: true, authError: null, fieldErrors: {} });
    try {
      const data = await meApi(token);
      set({
        token,
        user: data.user,
        userRole: data.user.role,
        schoolId: data.schoolId,
        schoolRole: data.schoolRole ?? null,
        isLoggedIn: true,
        isAuthLoading: false,
      });
    } catch {
      setStoredToken(null);
      resetAuthState();
    }
  },

  login: async ({ email, password }) => {
    set({ isAuthLoading: true, authError: null, fieldErrors: {} });
    try {
      const data = await loginApi({ email, password });
      clearSessionData({ keepLocation: true }); // never carry the previous user's caches into this session
      setStoredToken(data.token);
      set({
        token: data.token,
        user: data.user,
        userRole: data.user.role,
        schoolId: data.schoolId,
        schoolRole: data.schoolRole ?? null,
        isLoggedIn: true,
        isAuthLoading: false,
      });
    } catch (err) {
      const fieldErrors = err instanceof ApiValidationError ? err.fieldErrors : {};
      set({
        isAuthLoading: false,
        authError: err instanceof Error ? err.message : "Login failed",
        fieldErrors,
      });
      throw err;
    }
  },

  register: async (payload) => {
    set({ isAuthLoading: true, authError: null, fieldErrors: {} });
    try {
      const data = await registerApi(payload);
      clearSessionData({ keepLocation: true });
      setStoredToken(data.token);
      set({
        token: data.token,
        user: data.user,
        userRole: data.user.role,
        schoolId: data.schoolId,
        schoolRole: data.schoolRole ?? null,
        isLoggedIn: true,
        isAuthLoading: false,
      });
    } catch (err) {
      const fieldErrors = err instanceof ApiValidationError ? err.fieldErrors : {};
      set({
        isAuthLoading: false,
        authError: err instanceof Error ? err.message : "Registration failed",
        fieldErrors,
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
    resetAuthState();
  },

  logoutAll: async () => {
    const token = get().token;
    if (token) await logoutAllApi(token);
    setStoredToken(null);
    resetAuthState();
  },
}));

const LOGGED_OUT_STATE = {
  token: null,
  user: null,
  userRole: null,
  schoolId: null,
  schoolRole: null,
  isLoggedIn: false,
  authError: null,
  fieldErrors: {},
  isAuthLoading: false,
} as const;

/** Local-only teardown: auth store, query cache, Echo, per-user storage. Token removal is the caller's job. */
function resetAuthState(): void {
  clearSessionData();
  useAuthStore.setState({ ...LOGGED_OUT_STATE });
}

// 401 anywhere (public pages included) must not leave the store "logged in".
setSessionEndHandler(resetAuthState);

// Cross-tab: logout (or login as someone else) in another tab is mirrored here.
if (typeof window !== "undefined") {
  window.addEventListener("storage", (e) => {
    if (e.key !== "driveiq_auth_token" && e.key !== null) return;
    const stored = getStoredToken();
    const current = useAuthStore.getState().token;
    if (stored === current) return;
    resetAuthState();
    if (stored) void useAuthStore.getState().hydrateAuth();
  });
}

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
