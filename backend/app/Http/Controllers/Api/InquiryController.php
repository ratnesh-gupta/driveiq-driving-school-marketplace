<?php

namespace App\Http\Controllers\Api;

use App\Events\InquiryCreated;
use App\Http\Controllers\Concerns\PagesResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInquiryRequest;
use App\Http\Requests\Api\UpdateInquiryRequest;
use App\Http\Resources\InquiryResource;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\Inquiry;
use App\Models\LeadStatusHistory;
use App\Support\Entitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InquiryController extends Controller
{
    use PagesResults;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'schoolId' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(Inquiry::STATUSES)],
            'followUpDue' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:newest,oldest_waiting'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        // BelongsToSchool global scope auto-filters for school users.
        $query = Inquiry::with('school');

        // Oldest unanswered first, so the longest-waiting leads are on top.
        if ($request->input('sort') === 'oldest_waiting') {
            $query->orderByRaw('first_responded_at IS NOT NULL')->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        if ($request->boolean('followUpDue')) {
            $query->whereIn('status', Inquiry::OPEN_STATUSES)
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now());
        }

        // Admins may still filter by schoolId explicitly.
        if ($request->filled('schoolId') && $request->user()?->isAdmin()) {
            $query->where('school_id', $request->input('schoolId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Name, phone or email (DIQ-1305).
        if ($request->filled('search')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($request->input('search'))).'%';
            $query->where(fn ($q) => $q->where('name', 'ilike', $term)
                ->orWhere('phone', 'ilike', $term)
                ->orWhere('email', 'ilike', $term));
        }

        [$rows, $headers] = $this->pageResults($query, $request);

        return response()->json(InquiryResource::collection($rows))->withHeaders($headers);
    }

    /**
     * GET /inquiries/summary: lead counts by status for the caller's school
     * (DIQ-1305). Charts use this instead of downloading every lead.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate(['schoolId' => ['nullable', 'integer']]);

        $query = Inquiry::query();
        if ($request->filled('schoolId') && $request->user()?->isAdmin()) {
            $query->where('school_id', $request->input('schoolId'));
        }

        $counts = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'byStatus' => collect(Inquiry::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)]),
            'total' => (int) $counts->sum(),
        ]);
    }

    public function store(StoreInquiryRequest $request): JsonResponse
    {
        $data = $request->toSnakeCase();

        // A signed-in learner enquiring for themself: lets the school link their
        // account when it converts this enquiry (DIQ-406).
        $user = $request->user('sanctum');
        if ($user?->isLearner()) {
            $data['user_id'] = $user->id;
        }

        $inquiry = Inquiry::withoutGlobalScope('school')->create($data);
        $inquiry->load('school');

        if ($user && $inquiry->whatsapp_opt_in_at) {
            Consent::create([
                'user_id' => $user->id,
                'purpose' => 'whatsapp_updates',
                'version' => WhatsAppPreferenceController::CONSENT_VERSION,
                'granted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        event(new InquiryCreated($inquiry));

        return response()->json(new InquiryResource($inquiry), 201);
    }

    public function update(UpdateInquiryRequest $request, int $id): JsonResponse
    {
        // Global scope ensures school users only resolve their own inquiries.
        $inquiry = Inquiry::find($id);

        if (! $inquiry) {
            return response()->json(['message' => 'Inquiry not found'], 404);
        }

        $oldValues = $inquiry->only(['status', 'lost_reason', 'message', 'channel']);
        $previousStatus = $inquiry->status;

        // Where the learner module is on the plan, "converted" means a learner
        // record exists, so it is only reached through Convert to learner.
        // Schools without the module may still mark offline enrolments.
        if ($request->input('status') === 'converted' && $previousStatus !== 'converted'
            && app(Entitlements::class)->allows((int) $inquiry->school_id, 'learners')) {
            throw ValidationException::withMessages([
                'status' => 'Use "Convert to learner" so the learner record is created.',
            ]);
        }

        $inquiry->fill($request->toSnakeCase());
        // A reason only belongs to a lost lead; a closed lead needs no follow-up.
        if ($inquiry->status !== 'lost') {
            $inquiry->lost_reason = null;
        }
        if (! in_array($inquiry->status, Inquiry::OPEN_STATUSES, true)) {
            $inquiry->next_follow_up_at = null;
        }
        $inquiry->save();
        if (in_array($inquiry->status, Inquiry::RESPONDED_STATUSES, true)) {
            $inquiry->markResponded();
        }
        $inquiry->load('school');

        if (array_key_exists('status', $request->toSnakeCase())
            && $previousStatus !== $inquiry->status) {
            LeadStatusHistory::create([
                'inquiry_id' => $inquiry->id,
                'from_status' => $previousStatus,
                'to_status' => $inquiry->status,
                'changed_by_id' => $request->user()?->id,
            ]);
        }

        AuditLog::log(
            'update',
            'Inquiry',
            $inquiry->id,
            $oldValues,
            $inquiry->only(['status', 'lost_reason', 'message', 'channel'])
        );

        return response()->json(new InquiryResource($inquiry));
    }
}
