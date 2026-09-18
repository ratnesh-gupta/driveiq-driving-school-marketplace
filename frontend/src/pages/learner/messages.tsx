import { LearnerLayout } from "@/components/layout/learner-layout";
import { MessagesPanel } from "@/pages/dashboard/messages";

export default function LearnerMessagesPage() {
  return (
    <LearnerLayout>
      <MessagesPanel title="Messages" />
    </LearnerLayout>
  );
}
