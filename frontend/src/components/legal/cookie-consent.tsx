import { useEffect, useState } from "react";
import { Link } from "wouter";
import { Button } from "@/components/ui/button";

const STORAGE_KEY = "driveiq_cookie_consent";

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
 * DriveIQ currently uses:
 * - localStorage for auth token + theme + this consent choice
 * - No third-party advertising cookies in the product today
 * Optional analytics may be enabled later only after "Accept all".
 */
export function CookieConsent() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    setVisible(getCookieConsent() === null);
  }, []);

  if (!visible) return null;

  const choose = (value: ConsentValue) => {
    setCookieConsent(value);
    setVisible(false);
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
          <Button variant="outline" size="sm" onClick={() => choose("essential")}>
            Essential only
          </Button>
          <Button size="sm" onClick={() => choose("accepted")}>
            Accept
          </Button>
        </div>
      </div>
    </div>
  );
}
