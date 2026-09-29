<?php

namespace App\Http\Controllers\Api;

use App\Events\InquiryCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreInquiryRequest;
use App\Http\Requests\Api\UpdateInquiryRequest;
use App\Http\Resources\InquiryResource;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\LeadStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'schoolId' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(Inquiry::STATUSES)],
            'followUpDue' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:newest,oldest_waiting'],
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

        return response()->json(InquiryResource::collection($query->get()));
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
