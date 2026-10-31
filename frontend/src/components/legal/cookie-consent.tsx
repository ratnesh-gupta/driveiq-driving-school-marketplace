import { useEffect, useState } from "react";
import { Link } from "wouter";
import { Button } from "@/components/ui/button";
import { deviceId } from "@/lib/consent";
import { postConsents } from "@/lib/ops-api";

const STORAGE_KEY = "driveiq_cookie_consent";
const COOKIE_NOTICE_VERSION = "1";

const REOPEN_EVENT = "driveiq:cookie-settings";

/** Reopens the cookie choice, e.g. from the footer "Cookie settings" link. */
export function openCookieSettings(): void {
  window.dispatchEvent(new Event(REOPEN_EVENT));
}

export type ConsentValue = "accepted" | "essential";

export function getCookieConsent(): ConsentValue | null {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    if (v === "accepted" || v === "essential") return v;
  } catch {
    /* ignore */
  }
  return null;
}

export function setCookieConsent(value: ConsentValue): void {
  try {
    localStorage.setItem(STORAGE_KEY, value);
  } catch {
    /* ignore */
  }
}

/**
 * DriveQ currently uses:
 * - localStorage for auth token + theme + this consent choice
 * - No third-party advertising cookies in the product today
 * Optional analytics may be enabled later only after "Accept all".
 */
export function CookieConsent() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    setVisible(getCookieConsent() === null);
    const reopen = () => setVisible(true);
    window.addEventListener(REOPEN_EVENT, reopen);
    return () => window.removeEventListener(REOPEN_EVENT, reopen);
  }, []);

  if (!visible) return null;

  const choose = (value: ConsentValue) => {
    setCookieConsent(value);
    setVisible(false);
    // Only an opt-in is a consent; "essential only" needs none (DIQ-604).
    if (value === "accepted") {
      void postConsents([{ purpose: "cookies_optional", version: COOKIE_NOTICE_VERSION }], deviceId()).catch(() => {
        /* the local choice still applies; no optional storage is in use today */
      });
    }
  };

  return (
    <div
      role="dialog"
      aria-label="Cookie and storage notice"
      className="fixed bottom-0 inset-x-0 z-[100] p-4 md:p-6"
    >
      <div className="mx-auto max-w-3xl rounded-2xl border bg-background shadow-lg p-4 md:p-5 flex flex-col gap-4 md:flex-row md:items-center">
        <div className="flex-1 text-sm text-muted-foreground">
          <p className="font-medium text-foreground mb-1">Cookies & local storage</p>
          <p>
            We use essential browser storage to keep you signed in and remember theme preferences.
            We do not use advertising cookies. Location is only requested when you choose
            &quot;Use my location&quot; for nearby school search.{" "}
            <Link href="/privacy" className="underline text-primary">
              Privacy Policy
            </Link>
            {" · "}
            <Link href="/terms" className="underline text-primary">
              Terms
            </Link>
          </p>
        </div>
        <div className="flex flex-wrap gap-2 shrink-0">
          {/* Same variant and size on purpose: rejecting must be as easy as accepting. */}
          <Button variant="outline" onClick={() => choose("essential")}>
            Reject optional
          </Button>
          <Button variant="outline" onClick={() => choose("accepted")}>
            Accept all
          </Button>
        </div>
      </div>
    </div>
  );
}
