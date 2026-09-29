import { createRoot } from "react-dom/client";
import { setAuthTokenGetter, setBaseUrl, setUnauthorizedHandler } from "@/api-client";
import { getStoredToken, handleUnauthorized } from "@/lib/auth-api";
import { useAuthStore } from "@/lib/store";
import { detectInitialLocale, persistLocale } from "@/i18n";
import App from "./App";
import "./index.css";

// VITE_API_BASE_URL may be the API origin ("http://host:8000") or already end
// in "/api" (docker-compose). The generated client's paths ("/schools", …)
// need the "/api" prefix; the hand-written clients strip it themselves.
const apiOrigin = ((import.meta.env.VITE_API_BASE_URL as string | undefined) ?? "").replace(/\/+$/, "").replace(/\/api$/i, "");
setBaseUrl(`${apiOrigin}/api`);
setAuthTokenGetter(() => getStoredToken());
setUnauthorizedHandler(handleUnauthorized);
void useAuthStore.getState().hydrateAuth();
persistLocale(detectInitialLocale());

createRoot(document.getElementById("root")!).render(<App />);
