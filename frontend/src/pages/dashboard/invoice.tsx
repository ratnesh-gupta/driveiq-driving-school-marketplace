import { useQuery } from "@tanstack/react-query";
import { Link, useParams } from "wouter";
import { ArrowLeft, Printer } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useSchoolId } from "@/hooks/use-school-id";
import { fetchSchoolInvoice } from "@/lib/ops-api";
import { PLAN_LABEL, formatInr } from "@/lib/plan";

/** Printable plan invoice (DIQ-804). Standalone page so it prints cleanly. */
export default function InvoicePage() {
  const params = useParams<{ id: string }>();
  const schoolId = useSchoolId();
  const { data: inv, isLoading, error } = useQuery({
    queryKey: ["platform-invoice", schoolId, params.id],
    queryFn: () => fetchSchoolInvoice(schoolId!, Number(params.id)),
    enabled: !!schoolId,
  });

  return (
    <div className="min-h-[100dvh] bg-muted/30 py-8 px-4 print:bg-white print:p-0">
      <div className="max-w-2xl mx-auto mb-4 flex justify-between print:hidden">
        <Button variant="ghost" size="sm" asChild>
          <Link href="/dashboard/billing"><ArrowLeft className="h-4 w-4 mr-1" /> Billing</Link>
        </Button>
        <Button size="sm" onClick={() => window.print()} disabled={!inv}><Printer className="h-4 w-4 mr-1" /> Print / save PDF</Button>
      </div>

      <div className="max-w-2xl mx-auto rounded-xl border bg-card p-8 print:border-0 print:shadow-none">
        {isLoading ? (
          <Skeleton className="h-96" />
        ) : error || !inv ? (
          <p className="text-sm text-destructive">{(error as Error)?.message ?? "Invoice not found"}</p>
        ) : (
          <>
            <div className="flex justify-between gap-6 flex-wrap">
              <div>
                <div className="text-2xl font-bold">Tax invoice</div>
                <div className="text-sm text-muted-foreground">{inv.invoiceNumber}</div>
                {inv.status !== "issued" && (
                  <div className={`mt-2 inline-block text-xs font-semibold uppercase px-2 py-0.5 rounded ${inv.status === "paid" ? "bg-green-100 text-green-800" : "bg-muted text-muted-foreground"}`}>
                    {inv.status}
                  </div>
                )}
              </div>
              <div className="text-sm text-right">
                <div className="font-semibold">{inv.seller?.name}</div>
                <div className="text-muted-foreground">{inv.seller?.address}</div>
                {inv.seller?.gstin && <div>GSTIN {inv.seller.gstin}</div>}
                <div className="text-muted-foreground">{inv.seller?.email}</div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4 text-sm mt-8">
              <div>
                <div className="text-xs text-muted-foreground uppercase">Billed to</div>
                <div className="font-medium">{inv.schoolName}</div>
                {inv.billedToGstin && <div>GSTIN {inv.billedToGstin}</div>}
              </div>
              <div className="text-right">
                <div><span className="text-muted-foreground">Issued</span> {new Date(inv.issuedAt).toLocaleDateString()}</div>
                <div><span className="text-muted-foreground">Due</span> {new Date(inv.dueAt).toLocaleDateString()}</div>
                {inv.paidAt && <div><span className="text-muted-foreground">Paid</span> {new Date(inv.paidAt).toLocaleDateString()}</div>}
              </div>
            </div>

            <table className="w-full text-sm mt-8">
              <thead className="border-b">
                <tr><th className="text-left py-2">Description</th><th className="text-right py-2">Amount</th></tr>
              </thead>
              <tbody>
                <tr className="border-b">
                  <td className="py-2">DriveIQ {PLAN_LABEL[inv.planCode]} plan · {inv.months} month{inv.months > 1 ? "s" : ""}</td>
                  <td className="py-2 text-right">{formatInr(inv.subtotal)}</td>
                </tr>
                <tr><td className="py-1 text-muted-foreground">GST @ {inv.gstRate}%</td><td className="py-1 text-right">{formatInr(inv.gstAmount)}</td></tr>
                <tr className="font-semibold"><td className="py-2">Total ({inv.currency})</td><td className="py-2 text-right">{formatInr(inv.total)}</td></tr>
              </tbody>
            </table>

            {inv.paymentReference && <p className="text-sm mt-6">Payment reference: <span className="font-mono">{inv.paymentReference}</span></p>}
            {inv.status === "issued" && inv.paymentInstructions && (
              <div className="text-sm mt-6">
                <div className="font-medium">How to pay</div>
                {inv.paymentInstructions.upi_id && <div>UPI: <span className="font-mono">{inv.paymentInstructions.upi_id}</span></div>}
                {inv.paymentInstructions.account_number && (
                  <div>
                    Bank: {inv.paymentInstructions.bank_name} · {inv.paymentInstructions.account_name} · A/c{" "}
                    <span className="font-mono">{inv.paymentInstructions.account_number}</span> · IFSC{" "}
                    <span className="font-mono">{inv.paymentInstructions.ifsc}</span>
                  </div>
                )}
                <div className="text-muted-foreground">Please quote {inv.invoiceNumber} with your payment.</div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
