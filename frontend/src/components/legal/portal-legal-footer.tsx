import { Link } from "wouter";

/** Compact legal strip for authenticated portals (all roles). */
export function PortalLegalFooter() {
  return (
    <div className="mt-8 pt-4 border-t text-xs text-muted-foreground flex flex-wrap gap-x-3 gap-y-1">
      <Link href="/privacy" className="hover:text-primary underline-offset-2 hover:underline">Privacy</Link>
      <Link href="/terms" className="hover:text-primary underline-offset-2 hover:underline">Terms</Link>
      <Link href="/privacy/data-request" className="hover:text-primary underline-offset-2 hover:underline">Data rights</Link>
      <span>Grievance: privacy@driveiq.in</span>
    </div>
  );
}
