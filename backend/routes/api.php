<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\LocalityController;
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

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/schools', [SchoolController::class, 'index']);
Route::get('/schools/featured', [SchoolController::class, 'featured']);
Route::get('/schools/slug/{slug}', [SchoolController::class, 'showBySlug']);
Route::get('/schools/{id}', [SchoolController::class, 'show'])->whereNumber('id');

Route::get('/localities', [LocalityController::class, 'index']);
Route::get('/localities/slug/{slug}', [LocalityController::class, 'showBySlug']);
Route::get('/localities/{id}', [LocalityController::class, 'show'])->whereNumber('id');

Route::get('/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store']);

Route::post('/inquiries', [InquiryController::class, 'store']);

Route::get('/packages', [PackageController::class, 'index']);

Route::get('/stats/overview', [StatsController::class, 'overview']);
Route::get('/stats/school/{schoolId}', [StatsController::class, 'school'])->whereNumber('schoolId');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/admin/ping', fn () => response()->json(['ok' => true]))->middleware('role:admin');
    Route::get('/school/ping', fn () => response()->json(['ok' => true]))->middleware('role:school,admin');

    Route::get('/inquiries', [InquiryController::class, 'index'])->middleware('role:school,admin');
    Route::patch('/inquiries/{id}', [InquiryController::class, 'update'])->whereNumber('id')->middleware('role:school,admin');

    Route::post('/packages', [PackageController::class, 'store'])->middleware('role:school,admin');
    Route::patch('/packages/{id}', [PackageController::class, 'update'])->whereNumber('id')->middleware('role:school,admin');
    Route::delete('/packages/{id}', [PackageController::class, 'delete'])->whereNumber('id')->middleware('role:school,admin');

    Route::patch('/reviews/{id}', [ReviewController::class, 'update'])->whereNumber('id')->middleware('role:school,admin');
    Route::delete('/reviews/{id}', [ReviewController::class, 'delete'])->whereNumber('id')->middleware('role:school,admin');

    Route::post('/schools', [SchoolController::class, 'store'])->middleware('role:admin');
    Route::patch('/schools/{id}', [SchoolController::class, 'update'])->whereNumber('id')->middleware('role:school,admin');
    Route::delete('/schools/{id}', [SchoolController::class, 'delete'])->whereNumber('id')->middleware('role:admin');

    Route::post('/localities', [LocalityController::class, 'store'])->middleware('role:admin');
});
