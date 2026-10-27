import { Button } from "@/components/ui/button";
import { ChevronLeft, ChevronRight } from "lucide-react";

/** "Showing 26–50 of 312" with Previous / Next (DIQ-1305). `page` starts at 1. */
export function Pager({
  page, perPage, total, onPage, noun = "results",
}: { page: number; perPage: number; total: number; onPage: (page: number) => void; noun?: string }) {
  if (total <= perPage && page === 1) {
    return total > 0 ? <p className="text-xs text-muted-foreground px-1 py-3">{total} {noun}</p> : null;
  }
  const pages = Math.max(1, Math.ceil(total / perPage));
  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = Math.min(total, page * perPage);

  return (
    <div className="flex items-center justify-between gap-3 px-1 py-3" data-testid="pager">
      <p className="text-xs text-muted-foreground">
        Showing <span className="tabular-nums">{from}–{to}</span> of <span className="tabular-nums">{total}</span> {noun}
      </p>
      <div className="flex items-center gap-2">
        <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => onPage(page - 1)} data-testid="pager-prev">
          <ChevronLeft className="h-4 w-4" /> Previous
        </Button>
        <span className="text-xs text-muted-foreground tabular-nums">{page} / {pages}</span>
        <Button variant="outline" size="sm" disabled={page >= pages} onClick={() => onPage(page + 1)} data-testid="pager-next">
          Next <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
