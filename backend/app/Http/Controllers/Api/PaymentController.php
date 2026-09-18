<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\School;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authSchool($request, $schoolId)) {
            return $deny;
        }

        $items = Payment::withoutGlobalScope('school')
            ->with(['learner:id,name', 'package:id,name,price'])
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Payment $p) => $this->serialize($p));

        return response()->json($items);
    }

    public function purchasePackage(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authSchool($request, $schoolId)) {
            return $deny;
        }

        $data = $request->validate([
            'learnerId' => ['required', 'integer'],
            'packageId' => ['required', 'integer'],
            'method' => ['nullable', 'string', 'in:manual,cash,upi,card,razorpay,bank'],
            'markPaid' => ['nullable', 'boolean'],
        ]);

        $result = $this->payments->purchasePackage(
            $schoolId,
            (int) $data['learnerId'],
            (int) $data['packageId'],
            $request->user(),
            $data['method'] ?? 'manual',
            (bool) ($data['markPaid'] ?? false)
        );

        AuditLog::log('purchase', 'Payment', $result['payment']->id, [], [
            'learner_id' => $data['learnerId'],
            'package_id' => $data['packageId'],
            'amount' => $result['payment']->amount,
        ]);

        return response()->json([
            'payment' => $this->serialize($result['payment']),
            'invoice' => [
                'id' => $result['invoice']->id,
                'invoiceNumber' => $result['invoice']->invoice_number,
                'amount' => $result['invoice']->amount,
                'status' => $result['invoice']->status,
            ],
            'gateway' => $result['gateway'],
        ], 201);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $payment = Payment::withoutGlobalScope('school')->find($id);
        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        if ($deny = $this->authSchool($request, (int) $payment->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'providerPaymentId' => ['nullable', 'string', 'max:100'],
        ]);

        $payment = $this->payments->markPaid(
            $payment,
            $request->user(),
            $data['providerPaymentId'] ?? null
        );

        return response()->json($this->serialize($payment));
    }

    public function markFailed(Request $request, int $id): JsonResponse
    {
        $payment = Payment::withoutGlobalScope('school')->find($id);
        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        if ($deny = $this->authSchool($request, (int) $payment->school_id)) {
            return $deny;
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payment = $this->payments->markFailed($payment, $data['notes'] ?? null);

        return response()->json($this->serialize($payment));
    }

    private function serialize(Payment $p): array
    {
        return [
            'id' => $p->id,
            'schoolId' => $p->school_id,
            'invoiceId' => $p->invoice_id,
            'learnerId' => $p->learner_id,
            'learnerName' => $p->learner?->name,
            'purpose' => $p->purpose,
            'packageId' => $p->package_id,
            'packageName' => $p->package?->name,
            'amount' => $p->amount,
            'currency' => $p->currency,
            'method' => $p->method,
            'status' => $p->status,
            'provider' => $p->provider,
            'providerOrderId' => $p->provider_order_id,
            'providerPaymentId' => $p->provider_payment_id,
            'receipt' => $p->receipt,
            'paidAt' => $p->paid_at?->toISOString(),
            'createdAt' => $p->created_at?->toISOString(),
            'notes' => $p->notes,
        ];
    }

    private function authSchool(Request $request, int $schoolId): ?JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }
        $user = $request->user();
        if ($user->isAdmin() || ($user->isSchool() && (int) $user->school_id === $schoolId)) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
