<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformInvoice;
use App\Models\School;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** DIQ-803: plan invoices, school side (owner requests) and admin side (settles). */
class BillingController extends Controller
{
    private const GSTIN = '/^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i';

    public function __construct(private BillingService $billing) {}

    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        return response()->json(
            PlatformInvoice::where('school_id', $schoolId)->orderByDesc('id')->get()->map(fn ($i) => $i->toApi(true))
        );
    }

    public function show(Request $request, int $schoolId, int $invoiceId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $invoice = PlatformInvoice::where('school_id', $schoolId)->find($invoiceId);

        return $invoice
            ? response()->json($invoice->toApi(true))
            : response()->json(['message' => 'Invoice not found'], 404);
    }

    /** Only the owner commits the school to a paid plan. */
    public function store(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId, ownerOnly: true)) {
            return $deny;
        }

        $data = $request->validate([
            'planCode' => ['required', 'string', Rule::in(config('plans.upgrade_order'))],
            'months' => ['required', 'integer', Rule::in(config('billing.term_months'))],
            'gstin' => ['nullable', 'string', 'regex:'.self::GSTIN],
        ]);

        $school = School::findOrFail($schoolId);
        if (! in_array($data['planCode'], config('plans.listing_types.'.$school->listing_type, []), true)) {
            return response()->json(['message' => 'Validation failed', 'errors' => ['planCode' => ['This plan is not available for your listing.']]], 422);
        }

        $invoice = $this->billing->requestInvoice(
            $school, $data['planCode'], (int) $data['months'], $data['gstin'] ?? null, $request->user()
        );

        return response()->json($invoice->toApi(true), 201);
    }

    public function cancel(Request $request, int $schoolId, int $invoiceId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId, ownerOnly: true)) {
            return $deny;
        }

        $invoice = PlatformInvoice::where('school_id', $schoolId)->find($invoiceId);
        if (! $invoice) {
            return response()->json(['message' => 'Invoice not found'], 404);
        }

        return response()->json($this->billing->void($invoice, 'Cancelled by school', $request->user())->toApi());
    }

    // ── Platform admin ──

    public function adminIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:issued,paid,void'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = PlatformInvoice::query()
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (PlatformInvoice $i) => $i->toApi()),
            'meta' => ['page' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function recordPayment(Request $request, int $invoiceId): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'paidAt' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $invoice = PlatformInvoice::findOrFail($invoiceId);
        $paidAt = isset($data['paidAt']) ? Carbon::parse($data['paidAt']) : null;

        return response()->json($this->billing->recordPayment($invoice, $data['reference'], $paidAt, $request->user())->toApi());
    }

    public function void(Request $request, int $invoiceId): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        return response()->json(
            $this->billing->void(PlatformInvoice::findOrFail($invoiceId), $data['reason'] ?? null, $request->user())->toApi()
        );
    }
}
