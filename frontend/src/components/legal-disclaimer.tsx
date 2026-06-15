import { Alert, AlertDescription } from "@/components/ui/alert";
import { AlertTriangle } from "lucide-react";
import { cn } from "@/lib/utils";

export function LegalDisclaimer({ className }: { className?: string }) {
  return (
    <Alert className={cn("bg-muted/50", className)}>
      <AlertTriangle className="h-4 w-4" />
      <AlertDescription>
        Rules and regulations are for informational purposes only. DriveIQ is
        not affiliated with any government agency. Please verify details on the{" "}
        <a
          href="https://parivahan.gov.in/parivahan/"
          target="_blank"
          rel="noopener noreferrer"
          className="font-medium underline underline-offset-4 hover:text-primary"
        >
          official government website
        </a>
        .
      </AlertDescription>
    </Alert>
  );
}
