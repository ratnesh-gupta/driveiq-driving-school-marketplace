<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Review;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolDashboardController extends Controller
{
    public function show(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authorizeSchool($request, $schoolId)) {
            return $deny;
        }

        $school = School::with('locality')->find($schoolId);

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
            ->whereIn('status', ['contacted', 'converted', 'enrolled'])
            ->count();

        $responseRate = $totalInquiries > 0
            ? round($contactedOrBeyond / $totalInquiries, 4)
            : 0.0;

        $converted = Inquiry::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->whereIn('status', ['converted', 'enrolled'])
            ->count();

        $conversionRate = $totalInquiries > 0
            ? round($converted / $totalInquiries, 4)
            : 0.0;

        $pendingReviews = Review::withoutGlobalScope('school')
            ->where('school_id', $schoolId)
            ->where('approved', false)
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
            'profileCompleteness' => (int) ($school->profile_completeness ?? 0),
            'metrics' => [
                'totalInquiries' => $totalInquiries,
                'pendingInquiries' => $pendingInquiries,
                'inquiriesThisMonth' => $inquiriesThisMonth,
                'inquiriesLastMonth' => $inquiriesLastMonth,
                'responseRate' => $responseRate,
                'conversionRate' => $conversionRate,
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
                ] : null,
                $pendingReviews > 0 ? [
                    'type' => 'pending_reviews',
                    'count' => $pendingReviews,
                    'label' => "{$pendingReviews} review(s) awaiting moderation",
                ] : null,
                ($school->profile_completeness ?? 0) < 80 ? [
                    'type' => 'complete_profile',
                    'count' => 1,
                    'label' => 'Complete your school profile',
                ] : null,
            ])),
        ]);
    }

    public function auditLogs(Request $request, int $schoolId): JsonResponse
    {
        if ($deny = $this->authorizeSchool($request, $schoolId)) {
            return $deny;
        }

        $logs = AuditLog::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'modelType' => $log->model_type,
                'modelId' => $log->model_id,
                'userId' => $log->user_id,
                'oldValues' => $log->old_values,
                'newValues' => $log->new_values,
                'createdAt' => $log->created_at?->toISOString(),
            ]);

        return response()->json($logs);
    }

    private function authorizeSchool(Request $request, int $schoolId): ?JsonResponse
    {
        if (! School::find($schoolId)) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $user = $request->user();
        if ($user->isAdmin()) {
            return null;
        }

        if ((int) $user->school_id !== $schoolId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }
}
