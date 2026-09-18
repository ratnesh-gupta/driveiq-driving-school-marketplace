import { InstructorLayout } from "@/components/layout/instructor-layout";
import { MessagesPanel } from "@/pages/dashboard/messages";

export default function InstructorMessagesPage() {
  return (
    <InstructorLayout>
      <MessagesPanel title="Messages" />
    </InstructorLayout>
  );
}
