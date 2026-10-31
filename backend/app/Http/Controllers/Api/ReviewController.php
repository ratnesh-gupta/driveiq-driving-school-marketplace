<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Http\Requests\Api\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\AuditLog;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\School;
use App\Services\ReviewEligibilityService;
use App\Support\TextSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ReviewController extends Controller
{
    private const LINK_INVALID = 'This review link is invalid, expired or already used.';

    public function __construct(
        private readonly ReviewEligibilityService $eligibility,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'schoolId' => ['nullable', 'integer'],
            'includePending' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $query = Review::withoutGlobalScope('school')
            ->with('school')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $user = $request->user('sanctum');
        $schoolId = $request->filled('schoolId') ? (int) $request->input('schoolId') : null;

        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        // The reviewed school's own staff and admins may see their school even when it
        // is not (yet) public; everyone else only sees published listings (BSEC-09).
        $privileged = $user && ($user->isAdmin() || ($user->isSchool() && $schoolId !== null && (int) $user->school_id === $schoolId));
        if (! $privileged) {
            $query->whereIn('school_id', School::query()->public()->select('schools.id'));
        }

        // Public default: only approved reviews. Pending (unmoderated) reviews
        // are visible to admins, and to a school only for its own reviews.
        if (! ($request->boolean('includePending') && $privileged)) {
            $query->where('approved', true);
        }

        $total = (clone $query)->count();
        $max = $privileged ? 1000 : 100;
        $limit = min((int) $request->input('limit', $privileged ? $max : 50), $max);
        $items = $query->offset((int) $request->input('offset', 0))->limit($limit)->get();

        return response()->json(ReviewResource::collection($items))->header('X-Total-Count', (string) $total);
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
        $data['learner_id'] = $check['learner_id'];
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

    /** Public: what a one-time inquiry review link is for (DIQ-407 path B). */
    public function showInquiryReview(string $token): JsonResponse
    {
        $inquiry = preg_match(ReviewEligibilityService::TOKEN_PATTERN, $token) ? $this->eligibility->inquiryForToken($token) : null;

        if (! $inquiry) {
            return $this->noStore(response()->json(['message' => self::LINK_INVALID], 404));
        }

        return $this->noStore(response()->json([
            'schoolId' => $inquiry->school_id,
            'schoolName' => $inquiry->school?->name,
            'schoolSlug' => $inquiry->school?->slug,
            'authorName' => $inquiry->name,
        ]));
    }

    /** The token is a bearer secret: never cache the response, never leak it via Referer. */
    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    /** Public: submit a review with a one-time inquiry link; no account needed. */
    public function storeViaInquiry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'authorName' => ['required', 'string', 'max:100'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $inquiry = preg_match(ReviewEligibilityService::TOKEN_PATTERN, $data['token']) ? $this->eligibility->inquiryForToken($data['token']) : null;

        if (! $inquiry) {
            return $this->noStore(response()->json(['message' => self::LINK_INVALID], 404));
        }

        $review = Review::withoutGlobalScope('school')->create([
            'school_id' => $inquiry->school_id,
            'inquiry_id' => $inquiry->id,
            'eligibility_source' => 'inquiry',
            'author_name' => TextSanitizer::plain($data['authorName'], 100) ?: 'Anonymous',
            'rating' => $data['rating'],
            'content' => TextSanitizer::clean($data['content'], 5000),
            'approved' => false, // moderation queue
        ]);

        // Single use.
        $inquiry->forceFill(['review_token_hash' => null, 'review_token_expires_at' => null])->save();

        AuditLog::log('create', 'Review', $review->id, [], [
            'school_id' => $review->school_id,
            'inquiry_id' => $inquiry->id,
            'rating' => $review->rating,
            'eligibility_source' => 'inquiry',
        ], (int) $review->school_id);

        return $this->noStore(response()->json(new ReviewResource($review->load('school')), 201));
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
        $review->school?->recalculateRating();

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
        $school = $review->school;
        $review->delete();
        $school?->recalculateRating();

        return response()->json(null, 204);
    }

    /** Reports from accounts younger than this do not count towards the auto-hide (BSEC-07). */
    public const REPORTER_MIN_AGE_DAYS = 7;

    public const AUTO_HIDE_AT = 3;

    public const REPORTS_PER_DAY = 20;

    public function report(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $review = Review::withoutGlobalScope('school')->find($id);

        // Unapproved reviews are not public: only admins and the reviewed school
        // may report them, and everyone else gets the same 404 as a missing id.
        if (! $review || (! $review->approved && ! $user->isAdmin() && (int) $user->school_id !== (int) $review->school_id)) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        if (! RateLimiter::attempt('review-reports:'.$user->id, self::REPORTS_PER_DAY, fn () => true, 86400)) {
            return response()->json(['message' => 'Too many reports today. Please try again tomorrow.'], 429);
        }

        if (ReviewReport::where('review_id', $id)->where('reporter_id', $user->id)->exists()) {
            return response()->json(['message' => 'You have already reported this review'], 422);
        }

        $report = ReviewReport::create([
            'review_id' => $id,
            'reporter_id' => $user->id,
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'status' => 'pending',
        ]);

        // Every report reaches the moderation queue and bumps report_count (admin
        // signal), but only distinct, established accounts from outside the reviewed
        // school (and not the review's own author) can hide a review on their own.
        $review->increment('report_count');

        $credible = ReviewReport::query()
            ->join('users', 'users.id', '=', 'review_reports.reporter_id')
            ->where('review_reports.review_id', $id)
            ->where('users.created_at', '<=', now()->subDays(self::REPORTER_MIN_AGE_DAYS))
            ->whereNull('users.deactivated_at')
            ->where(fn ($q) => $q->whereNull('users.school_id')->orWhere('users.school_id', '!=', $review->school_id))
            ->when($review->user_id, fn ($q) => $q->where('users.id', '!=', $review->user_id))
            ->count('review_reports.reporter_id');

        $autoHidden = false;
        if ($credible >= self::AUTO_HIDE_AT && $review->approved) {
            $review->update(['approved' => false]);
            $review->school?->recalculateRating();
            $autoHidden = true;
        }

        AuditLog::log('report', 'Review', $review->id, [], [
            'report_id' => $report->id,
            'reason' => $report->reason,
            'credible_reports' => $credible,
            'auto_hidden' => $autoHidden,
        ], (int) $review->school_id);

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
