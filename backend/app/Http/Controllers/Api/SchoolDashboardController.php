<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\InstructorDocument;
use App\Models\Learner;
use App\Models\LearnerDocument;
use App\Models\LeaveRequest;
use App\Models\Review;
use App\Models\School;
use App\Models\VehicleDocument;
use App\Services\LeadResponseStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolDashboardController extends Controller
{
    public function show(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId)) {
            return $deny;
        }

        $school = School::with('locality')->find($schoolId);
        // Scored live so the bar and the "missing" list always agree.
        $completeness = $school->calculateProfileCompleteness();

        $now = now();
        $startThisMonth = $now->copy()->startOfMonth();
        $startLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endLastMonth = $now->copy()->subMonth()->endOfMonth();

        $inquiriesThisMonth = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('created_at', '>=', $startThisMonth)
            ->count();

        $inquiriesLastMonth = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->whereBetween('created_at', [$startLastMonth, $endLastMonth])
            ->count();

        $totalInquiries = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->count();

        $pendingInquiries = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('status', 'pending')
            ->count();

        $contactedOrBeyond = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->whereIn('status', Inquiry::RESPONDED_STATUSES)
            ->count();

        $responseRate = $totalInquiries > 0
            ? round($contactedOrBeyond / $totalInquiries, 4)
            : 0.0;

        $converted = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('status', 'converted')
            ->count();

        $conversionRate = $totalInquiries > 0
            ? round($converted / $totalInquiries, 4)
            : 0.0;

        $pendingReviews = Review::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('approved', false)
            ->count();

        $learnersThisMonth = Learner::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->where('created_at', '>=', $startThisMonth)->count();
        $learnersLastMonth = Learner::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->whereBetween('created_at', [$startLastMonth, $endLastMonth])->count();

        // Operational to-dos; each links to the page where it is resolved.
        $pendingLeave = LeaveRequest::withoutGlobalScope('school')->where('school_id', $schoolId)->where('status', 'pending')->count();
        $unassignedLearners = Learner::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->where('status', 'active')->whereNull('assigned_instructor_id')->count();
        $documentsToReview = LearnerDocument::withoutGlobalScope('school')->where('school_id', $schoolId)->where('status', 'uploaded')->count()
            + InstructorDocument::withoutGlobalScope('school')->where('school_id', $schoolId)->where('status', 'uploaded')->count();
        $expiredVehiclePapers = VehicleDocument::withoutGlobalScope('school')->where('school_id', $schoolId)
            ->whereNotNull('expiry_date')->where('expiry_date', '<', now()->toDateString())
            ->whereHas('vehicle', fn ($q) => $q->withoutGlobalScope('school')->where('status', '!=', 'retired'))
            ->count();

        $recentInquiries = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'name', 'phone', 'status', 'vehicle_type', 'created_at'])
            ->map(fn (Inquiry $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'phone' => $i->phone,
                'status' => $i->status,
                'vehicleType' => $i->vehicle_type,
                'createdAt' => $i->created_at?->toISOString(),
            ]);

        return response()->json([
            'schoolId' => $schoolId,
            'profileCompleteness' => $completeness,
            'missingProfileFields' => $school->missingProfileFields(),
            'metrics' => [
                'totalInquiries' => $totalInquiries,
                'pendingInquiries' => $pendingInquiries,
                'inquiriesThisMonth' => $inquiriesThisMonth,
                'inquiriesLastMonth' => $inquiriesLastMonth,
                'learnersThisMonth' => $learnersThisMonth,
                'learnersLastMonth' => $learnersLastMonth,
                'responseRate' => $responseRate,
                'conversionRate' => $conversionRate,
                'responseTime' => app(LeadResponseStats::class)->summary($schoolId),
                'pendingReviews' => $pendingReviews,
                'rating' => (float) ($school->rating ?? 0),
                'reviewCount' => (int) ($school->review_count ?? 0),
            ],
            'recentInquiries' => $recentInquiries,
            'pendingTasks' => array_values(array_filter([
                $pendingInquiries > 0 ? [
                    'type' => 'unresponded_leads',
                    'count' => $pendingInquiries,
                    'label' => "{$pendingInquiries} unresponded lead(s)",
                    'href' => '/dashboard/leads',
                ] : null,
                $pendingLeave > 0 ? [
                    'type' => 'pending_leave',
                    'count' => $pendingLeave,
                    'label' => "{$pendingLeave} leave request(s) to review",
                    'href' => '/dashboard/schedules',
                ] : null,
                $unassignedLearners > 0 ? [
                    'type' => 'unassigned_learners',
                    'count' => $unassignedLearners,
                    'label' => "{$unassignedLearners} active learner(s) without a trainer",
                    'href' => '/dashboard/learners',
                ] : null,
                $documentsToReview > 0 ? [
                    'type' => 'documents_to_review',
                    'count' => $documentsToReview,
                    'label' => "{$documentsToReview} uploaded document(s) to verify",
                    'href' => '/dashboard/learners',
                ] : null,
                $expiredVehiclePapers > 0 ? [
                    'type' => 'expired_vehicle_papers',
                    'count' => $expiredVehiclePapers,
                    'label' => "{$expiredVehiclePapers} expired vehicle paper(s)",
                    'href' => '/dashboard/vehicles',
                ] : null,
                $pendingReviews > 0 ? [
                    'type' => 'pending_reviews',
                    'count' => $pendingReviews,
                    'label' => "{$pendingReviews} review(s) awaiting moderation",
                    'href' => '/dashboard/reviews',
                ] : null,
                $completeness < 80 ? [
                    'type' => 'complete_profile',
                    'count' => 1,
                    'label' => 'Complete your school profile',
                    'href' => '/dashboard/profile',
                ] : null,
            ])),
        ]);
    }

    /**
     * The school's audit trail (DIQ-912). Owner only: it shows who changed
     * what across the team, including managers' own actions.
     */
    public function auditLogs(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->access()->school($request, $schoolId, ownerOnly: true)) {
            return $deny;
        }

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:50'],
            'modelType' => ['nullable', 'string', 'max:100'],
            'userId' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = AuditLog::query()
            ->where('school_id', $schoolId)
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['modelType'] ?? null, fn ($q, $v) => $q->where('model_type', $v))
            ->when($filters['userId'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json(AuditLogController::present($page));
    }
}
