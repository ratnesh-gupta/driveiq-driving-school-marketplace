<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\LocalityController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/healthz', fn () => response()->json([
    'status' => 'ok',
    'service' => 'driveiq-backend',
    'timestamp' => now()->toIso8601String(),
]));

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

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
    ->middleware('throttle:20,1');

Route::get('/packages', [PackageController::class, 'index']);

Route::prefix('stats')->group(function (): void {
    Route::get('/overview', [StatsController::class, 'overview']);
    Route::get('/school/{schoolId}', [StatsController::class, 'school'])->whereNumber('schoolId');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function (): void {

    // Auth profile
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Admin-only pings
    Route::get('/admin/ping', fn () => response()->json(['ok' => true]))->middleware('role:admin');
    Route::get('/school/ping', fn () => response()->json(['ok' => true]))->middleware('role:school,admin');

    // Admin-only routes
    Route::middleware('role:admin')->group(function (): void {
        Route::post('/schools', [SchoolController::class, 'store']);
        Route::delete('/schools/{id}', [SchoolController::class, 'delete'])->whereNumber('id');
        Route::post('/localities', [LocalityController::class, 'store']);
    });

    // School owner + Admin routes
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
