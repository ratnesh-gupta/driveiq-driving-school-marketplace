<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\DataSubjectRequest;
use App\Models\User;
use App\Notifications\DataRequestVerification;
use App\Services\ErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class DataSubjectRequestController extends Controller
{
    /** Hours the e-mailed confirmation link stays valid (BSEC-13). */
    private const VERIFY_HOURS = 48;

    /** Statutory-style handling window shown to admins as due_at. */
    private const DUE_DAYS = 30;

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'requestType' => ['required', 'string', 'in:access,correction,deletion,nomination,other'],
            'details' => ['nullable', 'string', 'max:5000'],
            'nomineeName' => ['nullable', 'string', 'max:255'],
            'nomineeEmail' => ['nullable', 'email', 'max:255'],
            'nomineeRelation' => ['nullable', 'string', 'max:100'],
        ]);

        if ($data['requestType'] === 'nomination' && empty($data['nomineeName'])) {
            return response()->json(['message' => 'Nominee name is required for nomination requests'], 422);
        }

        $email = strtolower($data['email']);
        $user = User::where('email', $email)->first();

        // BSEC-13: the confirmation mail goes to a third party, so cap it per address.
        if (RateLimiter::tooManyAttempts('dsr-email:'.sha1($email), 3)) {
            return response()->json(['message' => 'Too many requests for this address. Try again tomorrow.'], 429);
        }
        RateLimiter::hit('dsr-email:'.sha1($email), 86400);

        $token = Str::random(48);

        $row = DataSubjectRequest::create([
            'name' => $data['name'],
            'email' => $email,
            'request_type' => $data['requestType'],
            'details' => $data['details'] ?? null,
            'nominee_name' => $data['nomineeName'] ?? null,
            'nominee_email' => isset($data['nomineeEmail']) ? strtolower($data['nomineeEmail']) : null,
            'nominee_relation' => $data['nomineeRelation'] ?? null,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_id' => $user?->id,
            'verification_token_hash' => hash('sha256', $token),
            'verification_expires_at' => now()->addHours(self::VERIFY_HOURS),
            'due_at' => now()->addDays(self::DUE_DAYS),
        ]);

        // No raw e-mail in the audit trail (BSEC-14): a hash is enough to correlate.
        AuditLog::log('data_subject_request', 'DataSubjectRequest', $row->id, [], [
            'request_type' => $row->request_type,
            'email_hash' => sha1($row->email),
        ]);

        Notification::route('mail', $row->email)->notify(new DataRequestVerification(
            config('app.frontend_url').'/data-request/verify?token='.$token,
            self::VERIFY_HOURS,
        ));

        return response()->json([
            'id' => $row->id,
            'status' => $row->status,
            'verified' => false,
            'message' => 'Request recorded. We emailed you a link: open it to confirm the request is yours. We cannot act on it until you do.',
        ], 201);
    }

    /** POST /data-requests/verify: the requester proves control of the e-mail address. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'min:20', 'max:100']]);

        $row = DataSubjectRequest::where('verification_token_hash', hash('sha256', $data['token']))->first();
        if (! $row || $row->verification_expires_at === null || $row->verification_expires_at->isPast()) {
            return response()->json(['message' => 'This confirmation link is invalid or has expired. Please submit the request again.'], 422);
        }

        $row->forceFill(['verified_at' => now(), 'verification_token_hash' => null, 'verification_expires_at' => null])->save();

        AuditLog::log('data_subject_request_verified', 'DataSubjectRequest', $row->id, [], ['email_hash' => sha1($row->email)]);

        return response()->json(['id' => $row->id, 'verified' => true, 'status' => $row->status]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->query('perPage', 100), 100));
        $page = max(1, (int) $request->query('page', 1));
        $total = DataSubjectRequest::query()->count();
        $rows = DataSubjectRequest::query()
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        // Consent history of the matching account, for access / deletion requests (DIQ-604).
        $consents = Consent::query()
            ->whereIn('user_id', $rows->pluck('user_id')->filter()->unique())
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        $items = $rows->map(fn (DataSubjectRequest $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'requestType' => $r->request_type,
            'details' => $r->details,
            'nomineeName' => $r->nominee_name,
            'nomineeEmail' => $r->nominee_email,
            'nomineeRelation' => $r->nominee_relation,
            'status' => $r->status,
            'verifiedAt' => $r->verified_at?->toISOString(),
            'dueAt' => $r->due_at?->toISOString(),
            'erasedAt' => $r->erased_at?->toISOString(),
            'userId' => $r->user_id,
            'consents' => ($consents[$r->user_id] ?? collect())->map(fn (Consent $c) => $c->toApi())->values(),
            'createdAt' => $r->created_at?->toISOString(),
        ]);

        return response()->json($items)->header('X-Total-Count', (string) $total);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = DataSubjectRequest::find($id);
        if (! $row) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,in_progress,completed,rejected'],
        ]);

        // BSEC-13: nothing is processed or fulfilled for an unproven requester.
        // Rejecting is always allowed.
        if (in_array($data['status'], ['in_progress', 'completed'], true) && $row->verified_at === null) {
            return response()->json(['message' => 'The requester has not confirmed their e-mail address yet.', 'code' => 'verification_required'], 409);
        }

        $previous = $row->status;
        $row->update([
            'status' => $data['status'],
            'handled_at' => now(),
            'handled_by' => $request->user()->id,
        ]);

        AuditLog::log('data_subject_request_status', 'DataSubjectRequest', $row->id,
            ['status' => $previous], ['status' => $row->status, 'request_type' => $row->request_type]);

        return response()->json([
            'id' => $row->id,
            'status' => $row->status,
            'handledAt' => $row->handled_at?->toISOString(),
        ]);
    }

    /**
     * POST /admin/data-requests/{id}/erase: fulfil a verified deletion request
     * (anonymisation, see ErasureService). Admin only; idempotent; audit logged.
     */
    public function erase(Request $request, int $id, ErasureService $erasure): JsonResponse
    {
        $row = DataSubjectRequest::find($id);
        if (! $row) {
            return response()->json(['message' => 'Not found'], 404);
        }
        if ($row->request_type !== 'deletion') {
            return response()->json(['message' => 'Only deletion requests can be erased.'], 422);
        }
        if ($row->verified_at === null) {
            return response()->json(['message' => 'The requester has not confirmed their e-mail address yet.', 'code' => 'verification_required'], 409);
        }

        // Control of this address was proven by the verification link.
        $user = User::whereRaw('lower(email) = ?', [$row->email])->first();
        $result = $user
            ? $erasure->erase($user, true, $row->id)
            : $erasure->eraseContact($row->email, $row->id);

        if ($result['status'] === 'blocked') {
            return response()->json(['message' => 'Erasure is blocked.', 'blockers' => $result['blockers']], 409);
        }

        DB::transaction(function () use ($row, $request, $result) {
            $previous = $row->status;
            $row->forceFill([
                'status' => 'completed',
                'handled_at' => now(),
                'handled_by' => $request->user()->id,
                'erased_at' => $row->erased_at ?? now(),
            ])->save();

            AuditLog::log('data_subject_request_erased', 'DataSubjectRequest', $row->id,
                ['status' => $previous], ['status' => 'completed', 'result' => $result['status'], 'counts' => $result['counts']]);
        });

        return response()->json([
            'id' => $row->id,
            'status' => $row->status,
            'result' => $result['status'],
            'counts' => $result['counts'],
            'erasedAt' => $row->erased_at?->toISOString(),
        ]);
    }
}
