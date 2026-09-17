<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\InstructorController;
use App\Http\Controllers\Api\LearnerController;
use App\Http\Controllers\Api\LocalityController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PackageController;
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
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

Route::prefix('schools')->group(function (): void {
    Route::get('/', [SchoolController::class, 'index']);
    Route::get('/featured', [SchoolController::class, 'featured']);
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
Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:10,1']);

Route::post('/inquiries', [InquiryController::class, 'store'])
    ->middleware('throttle:10,1');

Route::get('/packages', [PackageController::class, 'index']);
Route::get('/plans', [SubscriptionController::class, 'plans']);
Route::get('/training-skills', [ProgressController::class, 'skillsCatalog']);

Route::prefix('stats')->group(function (): void {
    Route::get('/overview', [StatsController::class, 'overview']);
    Route::get('/school/{schoolId}', [StatsController::class, 'school'])->whereNumber('schoolId');
});

Route::middleware('auth:sanctum')->group(function (): void {

    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::post('/reviews/{id}/report', [ReviewController::class, 'report'])
        ->whereNumber('id')
        ->middleware('throttle:10,1');

    Route::get('/admin/ping', fn () => response()->json(['ok' => true]))->middleware('role:admin');
    Route::get('/school/ping', fn () => response()->json(['ok' => true]))->middleware('role:school,admin');

    Route::middleware('role:instructor,school,admin')->group(function (): void {
        Route::get('/instructor/me', [InstructorController::class, 'me']);
        Route::post('/schedules/{id}/attendance', [ScheduleController::class, 'markAttendance'])->whereNumber('id');
        Route::put('/learners/{id}/progress', [ProgressController::class, 'update'])->whereNumber('id');
    });

    Route::middleware('role:learner,school,admin,instructor')->group(function (): void {
        Route::get('/learner/me', [LearnerController::class, 'me']);
        Route::post('/learners/{id}/documents', [LearnerController::class, 'addDocument'])->whereNumber('id');
        Route::get('/learners/{id}/progress', [ProgressController::class, 'show'])->whereNumber('id');
        Route::get('/learners/{id}/sessions', [ProgressController::class, 'sessionHistory'])->whereNumber('id');
        Route::get('/learners/{id}/driving-tests', [ProgressController::class, 'listTests'])->whereNumber('id');
    });

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/schools', [SchoolController::class, 'store']);
        Route::delete('/schools/{id}', [SchoolController::class, 'delete'])->whereNumber('id');
        Route::post('/localities', [LocalityController::class, 'store']);

        Route::get('/review-reports', [ReviewController::class, 'reports']);
        Route::patch('/review-reports/{id}', [ReviewController::class, 'resolveReport'])->whereNumber('id');

        Route::get('/admin/subscriptions/overview', [SubscriptionController::class, 'overview']);
        Route::post('/admin/subscriptions', [SubscriptionController::class, 'assign']);
        Route::post('/admin/subscriptions/{schoolId}/cancel', [SubscriptionController::class, 'cancel'])
            ->whereNumber('schoolId');
    });

    Route::middleware('role:school,admin')->group(function (): void {
        Route::patch('/schools/{id}', [SchoolController::class, 'update'])->whereNumber('id');

        Route::get('/inquiries', [InquiryController::class, 'index']);
        Route::patch('/inquiries/{id}', [InquiryController::class, 'update'])->whereNumber('id');
        Route::post('/inquiries/{id}/convert', [LearnerController::class, 'convertInquiry'])->whereNumber('id');

        Route::post('/packages', [PackageController::class, 'store']);
        Route::patch('/packages/{id}', [PackageController::class, 'update'])->whereNumber('id');
        Route::delete('/packages/{id}', [PackageController::class, 'delete'])->whereNumber('id');

        Route::patch('/reviews/{id}', [ReviewController::class, 'update'])->whereNumber('id');
        Route::delete('/reviews/{id}', [ReviewController::class, 'delete'])->whereNumber('id');

        Route::get('/schools/{id}/dashboard', [SchoolDashboardController::class, 'show'])->whereNumber('id');
        Route::get('/schools/{id}/audit-logs', [SchoolDashboardController::class, 'auditLogs'])->whereNumber('id');
        Route::get('/schools/{id}/settings', [SchoolSettingsController::class, 'show'])->whereNumber('id');
        Route::put('/schools/{id}/settings', [SchoolSettingsController::class, 'update'])->whereNumber('id');
        Route::get('/schools/{id}/team', [SchoolTeamController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/team', [SchoolTeamController::class, 'invite'])->whereNumber('id');
        Route::delete('/schools/{id}/team/{memberId}', [SchoolTeamController::class, 'remove'])
            ->whereNumber(['id', 'memberId']);

        Route::get('/schools/{id}/subscription', [SubscriptionController::class, 'showForSchool'])
            ->whereNumber('id');

        Route::get('/schools/{id}/instructors', [InstructorController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/instructors', [InstructorController::class, 'store'])->whereNumber('id');
        Route::get('/instructors/{id}', [InstructorController::class, 'show'])->whereNumber('id');
        Route::patch('/instructors/{id}', [InstructorController::class, 'update'])->whereNumber('id');
        Route::delete('/instructors/{id}', [InstructorController::class, 'destroy'])->whereNumber('id');

        Route::get('/instructors/{id}/documents', [InstructorController::class, 'listDocuments'])->whereNumber('id');
        Route::post('/instructors/{id}/documents', [InstructorController::class, 'addDocument'])->whereNumber('id');
        Route::patch('/instructors/{id}/documents/{docId}', [InstructorController::class, 'updateDocument'])
            ->whereNumber(['id', 'docId']);

        Route::get('/schools/{id}/vehicles', [VehicleController::class, 'index'])->whereNumber('id');
        Route::post('/schools/{id}/vehicles', [VehicleController::class, 'store'])->whereNumber('id');
        Route::patch('/vehicles/{id}', [VehicleController::class, 'update'])->whereNumber('id');
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
        Route::get('/learners/{id}/documents', [LearnerController::class, 'listDocuments'])->whereNumber('id');
        Route::patch('/learners/{id}/documents/{docId}', [LearnerController::class, 'updateDocument'])
            ->whereNumber(['id', 'docId']);

        // Phase 8 — driving tests (school managed)
        Route::post('/learners/{id}/driving-tests', [ProgressController::class, 'createTest'])->whereNumber('id');
        Route::patch('/driving-tests/{id}', [ProgressController::class, 'updateTest'])->whereNumber('id');
    });
});
