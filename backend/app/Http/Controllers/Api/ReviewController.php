<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Http\Requests\Api\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\AuditLog;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Services\ReviewEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'schoolId' => ['nullable', 'integer'],
            'includePending' => ['nullable', 'boolean'],
        ]);

        $query = Review::withoutGlobalScope('school')
            ->with('school')
            ->orderByDesc('created_at');

        if ($request->filled('schoolId')) {
            $query->where('school_id', $request->input('schoolId'));
        }

        // Public default: only approved reviews unless school/admin asks for pending.
        $user = $request->user('sanctum');
        $includePending = $request->boolean('includePending')
            && $user
            && (method_exists($user, 'isAdmin') && $user->isAdmin()
                || method_exists($user, 'isSchool') && $user->isSchool());

        if (! $includePending) {
            $query->where('approved', true);
        }

        return response()->json(ReviewResource::collection($query->get()));
    }

    public function store(StoreReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->toSnakeCase();
        $schoolId = (int) $data['school_id'];

        $check = $this->eligibility->check($user, $schoolId);

        if (! $check['eligible']) {
            return response()->json([
                'message' => $check['message'] ?? 'Not eligible to review this school',
            ], 403);
        }

        $data['user_id'] = $user->id;
        $data['inquiry_id'] = $check['inquiry_id'];
        $data['eligibility_source'] = $check['source'];
        $data['approved'] = false; // moderation queue by default
        $data['author_name'] = $data['author_name'] ?? $user->name;

        $review = Review::withoutGlobalScope('school')->create($data);
        $review->load('school');

        AuditLog::log('create', 'Review', $review->id, [], $review->only([
            'school_id', 'user_id', 'rating', 'eligibility_source',
        ]));

        return response()->json(new ReviewResource($review), 201);
    }

    public function update(UpdateReviewRequest $request, int $id): JsonResponse
    {
        $review = Review::find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $oldValues = $review->only(['approved', 'rating', 'content']);
        $review->fill($request->toSnakeCase());
        $review->save();
        $review->load('school');

        AuditLog::log('update', 'Review', $review->id, $oldValues, $review->only(['approved', 'rating', 'content']));

        return response()->json(new ReviewResource($review));
    }

    public function delete(int $id): JsonResponse
    {
        $review = Review::find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        AuditLog::log('delete', 'Review', $review->id, $review->toArray(), []);
        $review->delete();

        return response()->json(null, 204);
    }

    public function report(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = Review::withoutGlobalScope('school')->find($id);

        if (! $review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        $reporterId = $request->user()->id;

        $existing = ReviewReport::where('review_id', $id)
            ->where('reporter_id', $reporterId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already reported this review'], 422);
        }

        $report = ReviewReport::create([
            'review_id' => $id,
            'reporter_id' => $reporterId,
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'status' => 'pending',
        ]);

        $review->increment('report_count');

        // Auto-unapprove when reports exceed threshold
        if ($review->report_count >= 3 && $review->approved) {
            $review->update(['approved' => false]);
        }

        AuditLog::log('report', 'Review', $review->id, [], [
            'report_id' => $report->id,
            'reason' => $report->reason,
        ]);

        return response()->json([
            'message' => 'Report submitted',
            'id' => $report->id,
        ], 201);
    }

    public function reports(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'string', 'in:pending,reviewed,dismissed'],
        ]);

        $query = ReviewReport::with(['review', 'reporter'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->limit(100)->get()->map(fn (ReviewReport $r) => [
            'id' => $r->id,
            'reviewId' => $r->review_id,
            'reason' => $r->reason,
            'details' => $r->details,
            'status' => $r->status,
            'reporterId' => $r->reporter_id,
            'createdAt' => $r->created_at?->toISOString(),
        ]));
    }

    public function resolveReport(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:reviewed,dismissed'],
        ]);

        $report = ReviewReport::find($id);

        if (! $report) {
            return response()->json(['message' => 'Report not found'], 404);
        }

        $report->update([
            'status' => $request->input('status'),
            'resolved_by_id' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return response()->json(['message' => 'Report updated', 'status' => $report->status]);
    }
}
