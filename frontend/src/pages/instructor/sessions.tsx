import { InstructorLayout } from "@/components/layout/instructor-layout";
import { CalendarDays } from "lucide-react";

export default function InstructorSessionsPage() {
  return (
    <InstructorLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">Sessions</h1>
        <p className="text-sm text-muted-foreground mt-1">Upcoming and past training sessions</p>
      </div>
      <div className="py-16 text-center text-muted-foreground rounded-xl border bg-card">
        <CalendarDays className="h-10 w-10 mx-auto mb-2 opacity-30" />
        Sessions assigned to you will appear here once scheduled by the school.
      </div>
    </InstructorLayout>
  );
}
