<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\LocalityController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StatsController;
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

    // Phase 2: report a review
    Route::post('/reviews/{id}/report', [ReviewController::class, 'report'])
        ->whereNumber('id')
        ->middleware('throttle:10,1');

    Route::get('/admin/ping', fn () => response()->json(['ok' => true]))->middleware('role:admin');
    Route::get('/school/ping', fn () => response()->json(['ok' => true]))->middleware('role:school,admin');

    Route::middleware('role:admin')->group(function (): void {
        Route::post('/schools', [SchoolController::class, 'store']);
        Route::delete('/schools/{id}', [SchoolController::class, 'delete'])->whereNumber('id');
        Route::post('/localities', [LocalityController::class, 'store']);

        Route::get('/review-reports', [ReviewController::class, 'reports']);
        Route::patch('/review-reports/{id}', [ReviewController::class, 'resolveReport'])->whereNumber('id');
    });

    Route::middleware('role:school,admin')->group(function (): void {
        Route::patch('/schools/{id}', [SchoolController::class, 'update'])->whereNumber('id');

        Route::get('/inquiries', [InquiryController::class, 'index']);
        Route::patch('/inquiries/{id}', [InquiryController::class, 'update'])->whereNumber('id');

        Route::post('/packages', [PackageController::class, 'store']);
        Route::patch('/packages/{id}', [PackageController::class, 'update'])->whereNumber('id');
        Route::delete('/packages/{id}', [PackageController::class, 'delete'])->whereNumber('id');

        Route::patch('/reviews/{id}', [ReviewController::class, 'update'])->whereNumber('id');
        Route::delete('/reviews/{id}', [ReviewController::class, 'delete'])->whereNumber('id');
    });
});
