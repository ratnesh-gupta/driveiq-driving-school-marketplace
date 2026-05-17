import { createRoot } from "react-dom/client";
import { setAuthTokenGetter, setBaseUrl } from "@workspace/api-client-react";
import { getStoredToken } from "@/lib/auth-api";
import { useAuthStore } from "@/lib/store";
import App from "./App";
import "./index.css";

setBaseUrl(import.meta.env.VITE_API_BASE_URL ?? null);
setAuthTokenGetter(() => getStoredToken());
void useAuthStore.getState().hydrateAuth();

createRoot(document.getElementById("root")!).render(<App />);
