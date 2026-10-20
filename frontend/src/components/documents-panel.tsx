import { useRef, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { toast } from "sonner";
import { Check, ExternalLink, FileText, Upload, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import {
  getDocumentLink,
  listDocuments,
  reviewDocument,
  uploadDocument,
  type DocumentOwnerKind,
  type DocumentRow,
} from "@/lib/ops-api";

const TYPES: Record<DocumentOwnerKind, { value: string; label: string }[]> = {
  learner: [
    { value: "aadhaar", label: "Aadhaar" },
    { value: "pan", label: "PAN" },
    { value: "photo", label: "Photo" },
    { value: "learner_license", label: "Learner licence" },
    { value: "medical", label: "Medical certificate" },
    { value: "other", label: "Other" },
  ],
  instructor: [
    { value: "driving_license", label: "Driving licence" },
    { value: "aadhaar", label: "Aadhaar" },
    { value: "pan", label: "PAN" },
    { value: "photo", label: "Photo" },
    { value: "certificate", label: "Certificate" },
  ],
  vehicle: [
    { value: "registration", label: "Registration (RC)" },
    { value: "insurance", label: "Insurance" },
    { value: "pollution", label: "Pollution (PUC)" },
    { value: "permit", label: "Permit" },
  ],
};

/** Mirrors the server rule (DocumentStorage::FILE_RULE). */
const ACCEPT = ".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp";
const MAX_BYTES = 5 * 1024 * 1024;

const STATUS_STYLE: Record<string, string> = {
  verified: "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300",
  valid: "bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300",
  rejected: "bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300",
  expired: "bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300",
};

type Props = {
  kind: DocumentOwnerKind;
  ownerId: number;
  /** School staff can verify / reject learner and instructor documents. */
  canReview?: boolean;
};

export function DocumentsPanel({ kind, ownerId, canReview = false }: Props) {
  const queryClient = useQueryClient();
  const queryKey = ["documents", kind, ownerId];
  const fileInput = useRef<HTMLInputElement>(null);
  const [type, setType] = useState(TYPES[kind][0].value);
  const [file, setFile] = useState<File | null>(null);
  const [expiryDate, setExpiryDate] = useState("");

  const { data, isLoading, error } = useQuery({
    queryKey,
    queryFn: () => listDocuments(kind, ownerId),
  });

  const upload = useMutation({
    mutationFn: () => uploadDocument(kind, ownerId, { type, file: file!, expiryDate: expiryDate || undefined }),
    onSuccess: () => {
      toast.success("Document uploaded");
      setFile(null);
      setExpiryDate("");
      if (fileInput.current) fileInput.current.value = "";
      queryClient.invalidateQueries({ queryKey });
    },
    onError: (e: Error) => toast.error(e.message),
  });

  const review = useMutation({
    mutationFn: ({ docId, status }: { docId: number; status: "verified" | "rejected" }) =>
      reviewDocument(kind as Exclude<DocumentOwnerKind, "vehicle">, ownerId, docId, status),
    onSuccess: () => queryClient.invalidateQueries({ queryKey }),
    onError: (e: Error) => toast.error(e.message),
  });

  async function openDocument(doc: DocumentRow) {
    // Open the tab synchronously so popup blockers allow it, then point it at
    // the signed URL once the API returns it.
    const win = window.open("", "_blank");
    try {
      const { url } = await getDocumentLink(kind, doc.id);
      if (win) {
        win.opener = null;
        win.location.href = url;
      } else {
        window.location.assign(url);
      }
    } catch (e) {
      win?.close();
      toast.error(e instanceof Error ? e.message : "Could not open document");
    }
  }

  function pickFile(f: File | null) {
    if (f && f.size > MAX_BYTES) {
      toast.error("Files must be 5 MB or smaller");
      if (fileInput.current) fileInput.current.value = "";
      return;
    }
    setFile(f);
  }

  const typeLabel = (value: string) => TYPES[kind].find((t) => t.value === value)?.label ?? value;

  return (
    <div className="space-y-4">
      <div className="rounded-xl border bg-card p-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] items-end">
        <div>
          <Label htmlFor={`doc-type-${kind}`}>Document type</Label>
          <select
            id={`doc-type-${kind}`}
            className="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
            value={type}
            onChange={(e) => setType(e.target.value)}
          >
            {TYPES[kind].map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </select>
        </div>
        <div>
          <Label htmlFor={`doc-file-${kind}`}>File (PDF or image, max 5 MB)</Label>
          <Input
            id={`doc-file-${kind}`}
            ref={fileInput}
            type="file"
            accept={ACCEPT}
            className="mt-1"
            onChange={(e) => pickFile(e.target.files?.[0] ?? null)}
          />
        </div>
        <Button disabled={!file || upload.isPending} onClick={() => upload.mutate()}>
          <Upload className="h-4 w-4 mr-1" /> {upload.isPending ? "Uploading…" : "Upload"}
        </Button>
        {kind !== "instructor" && (
          <div className="sm:col-span-3 sm:max-w-xs">
            <Label htmlFor={`doc-expiry-${kind}`}>Expiry date (optional)</Label>
            <Input
              id={`doc-expiry-${kind}`}
              type="date"
              className="mt-1"
              value={expiryDate}
              onChange={(e) => setExpiryDate(e.target.value)}
            />
          </div>
        )}
      </div>

      {isLoading ? (
        <div className="space-y-2">{Array.from({ length: 2 }).map((_, i) => <Skeleton key={i} className="h-14 rounded-lg" />)}</div>
      ) : error ? (
        <div className="py-8 text-center text-sm text-destructive">{(error as Error).message}</div>
      ) : !data?.length ? (
        <div className="py-10 text-center text-sm text-muted-foreground">
          <FileText className="h-8 w-8 mx-auto mb-2 opacity-30" />
          No documents yet.
        </div>
      ) : (
        <div className="rounded-xl border bg-card divide-y">
          {data.map((doc) => (
            <div key={doc.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
              <FileText className="h-4 w-4 text-muted-foreground shrink-0" />
              <div className="min-w-0 flex-1">
                <div className="text-sm font-medium">{typeLabel(doc.type)}</div>
                <div className="text-xs text-muted-foreground truncate">
                  {[doc.fileName ?? "No file", doc.expiryDate ? `expires ${doc.expiryDate}` : null]
                    .filter(Boolean)
                    .join(" · ")}
                </div>
              </div>
              {doc.expiringSoon && (
                <span className="text-xs px-2 py-0.5 rounded-full font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                  expires soon
                </span>
              )}
              <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${STATUS_STYLE[doc.status] ?? "bg-muted"}`}>
                {doc.status}
              </span>
              {doc.hasFile && (
                <Button size="sm" variant="outline" onClick={() => openDocument(doc)}>
                  <ExternalLink className="h-3.5 w-3.5 mr-1" /> Open
                </Button>
              )}
              {canReview && kind !== "vehicle" && doc.hasFile && doc.status !== "verified" && (
                <Button
                  size="sm"
                  variant="outline"
                  disabled={review.isPending}
                  onClick={() => review.mutate({ docId: doc.id, status: "verified" })}
                >
                  <Check className="h-3.5 w-3.5 mr-1" /> Verify
                </Button>
              )}
              {canReview && kind !== "vehicle" && doc.hasFile && doc.status !== "rejected" && (
                <Button
                  size="sm"
                  variant="ghost"
                  disabled={review.isPending}
                  onClick={() => review.mutate({ docId: doc.id, status: "rejected" })}
                >
                  <X className="h-3.5 w-3.5 mr-1" /> Reject
                </Button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/** A "Documents" button that opens the panel in a dialog (dashboard lists). */
export function DocumentsDialog({ title, ...props }: Props & { title: string }) {
  const [open, setOpen] = useState(false);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="sm" variant="outline">
          <FileText className="h-3.5 w-3.5 mr-1" /> Documents
        </Button>
      </DialogTrigger>
      <DialogContent className="max-w-2xl max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>Private files, opened through links that expire after a few minutes.</DialogDescription>
        </DialogHeader>
        {open && <DocumentsPanel {...props} />}
      </DialogContent>
    </Dialog>
  );
}
