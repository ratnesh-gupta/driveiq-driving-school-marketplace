import { useQuery } from "@tanstack/react-query";
import { Link } from "wouter";
import { FileText } from "lucide-react";
import { LearnerLayout } from "@/components/layout/learner-layout";
import { DocumentsPanel } from "@/components/documents-panel";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { fetchLearnerMe } from "@/lib/ops-api";

export default function LearnerDocumentsPage() {
  const { data, isLoading } = useQuery({
    queryKey: ["learner", "me"],
    queryFn: fetchLearnerMe,
  });

  const learnerId = (data?.learner as { id?: number } | undefined)?.id;

  return (
    <LearnerLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">My documents</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Upload ID, licence and medical documents for your school to verify. Files are private to you and your school.
        </p>
      </div>

      {isLoading ? (
        <Skeleton className="h-40 rounded-xl" />
      ) : !learnerId ? (
        <div className="py-16 text-center text-muted-foreground">
          <FileText className="h-10 w-10 mx-auto mb-2 opacity-30" />
          <p>You can upload documents once a driving school enrols you.</p>
          <Button asChild className="mt-4" size="sm">
            <Link href="/search">Find a school</Link>
          </Button>
        </div>
      ) : (
        <DocumentsPanel kind="learner" ownerId={learnerId} />
      )}
    </LearnerLayout>
  );
}
