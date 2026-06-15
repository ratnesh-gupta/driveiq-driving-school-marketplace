import { Progress } from "@/components/ui/progress";
import { Card, CardContent } from "@/components/ui/card";
import { CheckCircle2, AlertCircle } from "lucide-react";

interface ProfileCompletenessProps {
  completeness: number;
  missingFields: string[];
}

export function ProfileCompleteness({ completeness, missingFields }: ProfileCompletenessProps) {
  const isComplete = completeness >= 90;

  return (
    <Card className={isComplete ? "border-green-200 dark:border-green-800" : ""}>
      <CardContent className="p-4 space-y-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            {isComplete ? (
              <CheckCircle2 className="h-4 w-4 text-green-500" />
            ) : (
              <AlertCircle className="h-4 w-4 text-amber-500" />
            )}
            <span className="font-semibold text-sm">
              Profile Completeness: {completeness}%
            </span>
          </div>
        </div>
        <Progress value={completeness} className="h-2" />
        {!isComplete && missingFields.length > 0 && (
          <p className="text-xs text-muted-foreground">
            Complete your profile to rank higher in search results. Missing:{" "}
            {missingFields.slice(0, 3).join(", ")}
            {missingFields.length > 3 && ` and ${missingFields.length - 3} more`}.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
