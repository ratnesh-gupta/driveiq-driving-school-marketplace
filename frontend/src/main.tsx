import { createRoot } from "react-dom/client";
import { setAuthTokenGetter, setBaseUrl, setUnauthorizedHandler } from "@/api-client";
import { getStoredToken, handleUnauthorized } from "@/lib/auth-api";
import { useAuthStore } from "@/lib/store";
import { detectInitialLocale, persistLocale } from "@/i18n";
import App from "./App";
import "./index.css";

setBaseUrl(import.meta.env.VITE_API_BASE_URL ?? null);
setAuthTokenGetter(() => getStoredToken());
setUnauthorizedHandler(handleUnauthorized);
void useAuthStore.getState().hydrateAuth();
persistLocale(detectInitialLocale());

createRoot(document.getElementById("root")!).render(<App />);
