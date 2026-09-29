<?php

use App\Http\Controllers\Api\AdminMonetizationController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ConsentController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\DataSubjectRequestController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\InstructorController;
use App\Http\Controllers\Api\LeadNoteController;
use App\Http\Controllers\Api\LearnerController;
use App\Http\Controllers\Api\LocalityController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\SchoolDashboardController;
use App\Http\Controllers\Api\SchoolSettingsController;
use App\Http\Controllers\Api\SchoolTeamController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', fn () => response()->json([
    'status' => 'ok',
    'service' => 'driveiq-backend',
    'timestamp' => now()->toIso8601String(),
]));

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');
});

Route::prefix('schools')->group(function (): void {
    Route::get('/', [SchoolController::class, 'index']);
    Route::get('/featured', [SchoolController::class, 'featured']);
    Route::get('/compare', [SchoolController::class, 'compare']);
    Route::get('/slug/{slug}', [SchoolController::class, 'showBySlug']);
    Route::get('/slug/{slug}/trainers', [InstructorController::class, 'publicTrainers']);
    Route::get('/{id}', [SchoolController::class, 'show'])->whereNumber('id');
});

Route::prefix('localities')->group(function (): void {
    Route::get('/', [LocalityController::class, 'index']);
    Route::get('/slug/{slug}', [LocalityController::class, 'showBySlug']);
    Route::get('/{id}', [LocalityController::class, 'show'])->whereNumber('id');
});

Route::get('/reviews', [ReviewController::class, 'index']);
// One-time review link from an inquiry confirmation email (DIQ-407).
Route::get('/reviews/via-inquiry/{token}', [ReviewController::class, 'showInquiryReview'])->middleware('throttle:public-lookups');
Route::post('/reviews/via-inquiry', [ReviewController::class, 'storeViaInquiry'])->middleware('throttle:public-forms');
Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:10,1']);

Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware('throttle:inquiries');

// Manager invitations (DIQ-403): usable before the invitee has an account.
Route::get('/team/invitations/{token}', [SchoolTeamController::class, 'showInvitation'])
    ->middleware('throttle:public-lookups');
Route::post('/team/accept', [SchoolTeamController::class, 'accept'])->middleware('throttle:public-forms');

Route::post('/data-requests', [DataSubjectRequestController::class, 'store'])
    ->middleware('throttle:public-forms');

Route::post('/contact', [ContactMessageController::class, 'store'])->middleware('throttle:public-forms');

// Consent records (DIQ-604). Optional auth: anonymous calls may only record
// the cookie banner choice.
Route::post('/consents', [ConsentController::class, 'store'])->middleware('throttle:consents');

Route::get('/packages', [PackageController::class, 'index']);
Route::get('/plans', [SubscriptionController::class, 'plans']);
Route::get('/training-skills', [ProgressController::class, 'skillsCatalog']);

Route::get('/stats/overview', [StatsController::class, 'overview']);

// Private document download (DIQ-601): only reachable through a short-lived
// signed URL issued by the authenticated /documents/{kind}/{id}/link.
Route::get('/documents/{kind}/{id}/file', [DocumentController::class, 'file'])
    ->whereIn('kind', ['learner', 'instructor', 'vehicle'])
    ->whereNumber('id')
    ->middleware('signed')
    ->name('documents.file');

Route::middleware('auth:sanctum')->group(function (): void {

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::get('/consents', [ConsentController::class, 'index']);
    Route::delete('/consents/{purpose}', [ConsentController::class, 'destroy']);

    Route::post('/reviews/{id}/report', [ReviewController::class, 'report'])
        ->whereNumber('id')
        ->middleware('throttle:10,1');

    Route::get('/documents/{kind}/{id}/link', [DocumentController::class, 'link'])
        ->whereIn('kind', ['learner', 'instructor', 'vehicle'])
        ->whereNumber('id')
        ->middleware('throttle:60,1');

    Route::get('/admin/ping', fn () => response()->json(['ok' => true]))->middleware('role:admin');
    Route::get('/school/ping', fn () => response()->json(['ok' => true]))->middleware('role:school,admin');

    Route::middleware('role:school,instructor,learner,admin')->group(function (): void {
        Route::get('/messages/threads', [MessageController::class, 'threads']);
        Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);
        Route::get('/messages/threads/{id}', [MessageController::class, 'show'])->whereNumber('id');
        Route::post('/messages', [MessageController::class, 'send'])->middleware('throttle:30,1');
        Route::post('/messages/threads/{id}/read', [MessageController::class, 'markRead'])->whereNumber('id');
    });

    // plan.features runs after the role check (DIQ-802; routes in config/plans.php).
    Route::middleware(['role:instructor,school,admin', 'plan.features'])->group(function (): void {
        Route::get('/instructor/me', [InstructorController::class, 'me']);
        Route::get('/instructor/sessions', [ScheduleController::class, 'instructorSessions']);
        Route::post('/schedules/{id}/attendance', [ScheduleController::class, 'markAttendance'])->whereNumber('id');
        Route::put('/learners/{id}/progress', [ProgressController::class, 'update'])->whereNumber('id');

        // Staff manage any instructor's documents; an instructor only their own.
        Route::get('/instructors/{id}/documents', [InstructorController::class, 'listDocuments'])->whereNumber('id');
        Route::post('/instructors/{id}/documents', [InstructorController::class, 'addDocument'])->whereNumber('id');
        Route::patch('/instructors/{id}/documents/{docId}', [InstructorController::class, 'updateDocument'])
            ->whereNumber(['id', 'docId']);
    });

    Route::middleware(['role:learner,school,admin,instructor', 'plan.features'])->group(function (): void {
        Route::get('/learner/me', [LearnerController::class, 'me']);
        Route::get('/learners/{id}/documents', [LearnerController::class, 'listDocuments'])->whereNumber('id');
        Route::post('/learners/{id}/documents', [LearnerController::class, 'addDocument'])->whereNumber('id');
        Route::get('/learners/{id}/progress', [ProgressController::class, 'show'])->whereNumber('id');
        Route::get('/learners/{id}/sessions', [ProgressController::class, 'sessionHistory'])->whereNumber('id');
        Route::get('/learners/{id}/driving-tests', [ProgressController::class, 'listTests'])->whereNumber('id');
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/schools', [SchoolController::class, 'store']);
        Route::delete('/schools/{id}', [SchoolController::class, 'delete'])->whereNumber('id');
        Route::post('/localities', [LocalityController::class, 'store']);

        // Review moderation is platform-admin only; schools can report a review instead.
        Route::patch('/reviews/{id}', [ReviewController::class, 'update'])->whereNumber('id');
        Route::delete('/reviews/{id}', [ReviewController::class, 'delete'])->whereNumber('id');

        Route::get('/review-reports', [ReviewController::class, 'reports']);
        Route::patch('/review-reports/{id}', [ReviewController::class, 'resolveReport'])->whereNumber('id');

        Route::get('/admin/subscriptions/overview', [SubscriptionController::class, 'overview']);
        Route::get('/admin/subscriptions', [AdminMonetizationController::class, 'subscriptions']);
        Route::get('/admin/placements', [AdminMonetizationController::class, 'placements']);
        Route::post('/admin/placements', [AdminMonetizationController::class, 'storePlacement']);
        Route::post('/admin/placements/{id}/end', [AdminMonetizationController::class, 'endPlacement'])->whereNumber('id');
        Route::get('/admin/marketplace-settings', [AdminMonetizationController::class, 'settings']);
        Route::put('/admin/marketplace-settings', [AdminMonetizationController::class, 'updateSettings']);
        Route::get('/admin/billing/invoices', [BillingController::class, 'adminIndex']);
        Route::post('/admin/billing/invoices/{id}/record-payment', [BillingController::class, 'recordPayment'])->whereNumber('id');
        Route::post('/admin/billing/invoices/{id}/void', [BillingController::class, 'void'])->whereNumber('id');
        Route::post('/admin/subscriptions', [SubscriptionController::class, 'assign']);
        Route::post('/admin/subscriptions/{schoolId}/cancel', [SubscriptionController::class, 'cancel'])
            ->whereNumber('schoolId');

        Route::get('/admin/analytics', [AnalyticsController::class, 'platform']);
        Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);

        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::patch('/admin/users/{id}', [AdminUserController::class, 'update'])->whereNumber('id');

        Route::get('/admin/contact-messages', [ContactMessageController::class, 'index']);
        Route::patch('/admin/contact-messages/{id}', [ContactMessageController::class, 'update'])->whereNumber('id');

        Route::get('/admin/data-requests', [DataSubjectRequestController::class, 'index']);
        Route::patch('/admin/data-requests/{id}', [DataSubjectRequestController::class, 'update'])->whereNumber('id');
    });

    Route::middleware(['role:school,admin', 'plan.features'])->group(function (): void {
        Route::patch('/schools/{id}', [SchoolController::class, 'update'])->whereNumber('id');

        Route::get('/inquiries', [InquiryController::class, 'index']);
        Route::patch('/inquiries/{id}', [InquiryController::class, 'update'])->whereNumber('id');
        Route::post('/inquiries/{id}/convert', [LearnerController::class, 'convertInquiry'])->whereNumber('id');
        Route::get('/inquiries/{id}/timeline', [LeadNoteController::class, 'timeline'])->whereNumber('id');
        Route::post('/inquiries/{id}/notes', [LeadNoteController::class, 'store'])->whereNumber('id');

        Route::post('/packages', [PackageController::class, 'store']);
        Route::patch('/packages/{id}', [PackageController::class, 'update'])->whereNumber('id');
        Route::delete('/packages/{id}', [PackageController::class, 'delete'])->whereNumber('id');

        Route::get('/schools/{id}/dashboard', [SchoolDashboardController::class, 'show'])->whereNumber('id');
        // A school's lead/review counts are private to that school and admins.
        Route::get('/stats/school/{schoolId}', [StatsController::class, 'school'])->whereNumber('schoolId');
        Route::get('/schools/{id}/audit-logs', [SchoolDashboardController::class, 'auditLogs'])->whereNumber('id');
        Route::get('/schools/{id}/settings', [SchoolSettingsController::class, 'show'])->whereNumber('id');
        Route::put('/schools/{id}/settings', [SchoolSettingsController::class, 'update'])->whereNumber('id');
        Route::get('/schools/{id}/team', [SchoolTeamController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/team', [SchoolTeamController::class, 'invite'])->whereNumber('id');
        Route::delete('/schools/{id}/team/{memberId}', [SchoolTeamController::class, 'remove'])
            ->whereNumber(['id', 'memberId']);

        Route::get('/schools/{id}/entitlements', [SubscriptionController::class, 'entitlements'])->whereNumber('id');
        Route::get('/schools/{id}/billing/invoices', [BillingController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/billing/invoices', [BillingController::class, 'store'])->whereNumber('id');
        Route::get('/schools/{id}/billing/invoices/{invoiceId}', [BillingController::class, 'show'])
            ->whereNumber(['id', 'invoiceId']);
        Route::post('/schools/{id}/billing/invoices/{invoiceId}/cancel', [BillingController::class, 'cancel'])
            ->whereNumber(['id', 'invoiceId']);
        Route::get('/schools/{id}/subscription', [SubscriptionController::class, 'showForSchool'])
            ->whereNumber('id');

        Route::get('/schools/{id}/instructors', [InstructorController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/instructors', [InstructorController::class, 'store'])->whereNumber('id');
        Route::get('/instructors/{id}', [InstructorController::class, 'show'])->whereNumber('id');
        Route::patch('/instructors/{id}', [InstructorController::class, 'update'])->whereNumber('id');
        Route::delete('/instructors/{id}', [InstructorController::class, 'destroy'])->whereNumber('id');

        Route::get('/schools/{id}/vehicles', [VehicleController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/vehicles', [VehicleController::class, 'store'])->whereNumber('id');
        Route::patch('/vehicles/{id}', [VehicleController::class, 'update'])->whereNumber('id');
        Route::get('/vehicles/{id}/documents', [VehicleController::class, 'listDocuments'])->whereNumber('id');
        Route::post('/vehicles/{id}/documents', [VehicleController::class, 'addDocument'])->whereNumber('id');

        Route::get('/schools/{id}/schedules', [ScheduleController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/schedules', [ScheduleController::class, 'store'])->whereNumber('id');
        Route::patch('/schedules/{id}', [ScheduleController::class, 'update'])->whereNumber('id');

        Route::get('/schools/{id}/leave-requests', [ScheduleController::class, 'listLeave'])->whereNumber('id');
        Route::post('/schools/{id}/leave-requests', [ScheduleController::class, 'requestLeave'])->whereNumber('id');
        Route::patch('/leave-requests/{id}', [ScheduleController::class, 'reviewLeave'])->whereNumber('id');

        Route::get('/schools/{id}/learners', [LearnerController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/learners', [LearnerController::class, 'store'])->whereNumber('id');
        Route::get('/learners/{id}', [LearnerController::class, 'show'])->whereNumber('id');
        Route::patch('/learners/{id}', [LearnerController::class, 'update'])->whereNumber('id');
        Route::post('/learners/{id}/assign', [LearnerController::class, 'assign'])->whereNumber('id');
        Route::patch('/learners/{id}/documents/{docId}', [LearnerController::class, 'updateDocument'])
            ->whereNumber(['id', 'docId']);

        Route::post('/learners/{id}/driving-tests', [ProgressController::class, 'createTest'])->whereNumber('id');
        Route::patch('/driving-tests/{id}', [ProgressController::class, 'updateTest'])->whereNumber('id');

        Route::get('/schools/{id}/analytics', [AnalyticsController::class, 'school'])->whereNumber('id');
        Route::get('/schools/{id}/analytics/instructors', [AnalyticsController::class, 'instructors'])->whereNumber('id');

        Route::get('/schools/{id}/payments', [PaymentController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/payments/package', [PaymentController::class, 'purchasePackage'])->whereNumber('id');
        Route::post('/payments/{id}/mark-paid', [PaymentController::class, 'markPaid'])->whereNumber('id');
        Route::post('/payments/{id}/mark-failed', [PaymentController::class, 'markFailed'])->whereNumber('id');
    });
});
