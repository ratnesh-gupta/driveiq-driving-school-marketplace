import { useState } from "react";
import { Link, useParams } from "wouter";
import { useMutation } from "@tanstack/react-query";
import { Button } from "@/components/ui/button";
import { unsubscribe } from "@/lib/acquisition-api";

/** DIQ-1105: unsubscribe link in outreach emails. Asks once, so link scanners cannot unsubscribe people. */
export default function UnsubscribePage() {
  const { token = "" } = useParams<{ token: string }>();
  const [done, setDone] = useState<string | null>(null);
  const run = useMutation({ mutationFn: () => unsubscribe(token), onSuccess: (r) => setDone(r.message) });

  return (
    <div className="min-h-screen flex items-center justify-center p-6 bg-background">
      <div className="w-full max-w-sm space-y-4">
        <Link href="/" className="font-bold text-xl text-primary">DriveQ</Link>
        {done ? (
          <p className="text-sm" data-testid="text-unsubscribed">{done}</p>
        ) : (
          <>
            <h1 className="text-xl font-semibold">Stop emails from DriveQ?</h1>
            <p className="text-sm text-muted-foreground">We will not email you about listing your school or trainer profile again, and we remove any listing we prepared for you.</p>
            {run.isError && <p className="text-sm text-destructive">{(run.error as Error).message}</p>}
            <Button onClick={() => run.mutate()} disabled={run.isPending} data-testid="button-unsubscribe">Unsubscribe</Button>
          </>
        )}
      </div>
    </div>
  );
}
