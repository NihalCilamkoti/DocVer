<?php

use App\Http\Controllers\Api\V1\Admin\ProviderManagementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ComparisonController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\ProviderHealthController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\UsageController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DocVer API Routes (v1)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // System Health Check (Load balancer / uptime monitoring probe)
    Route::get('health', [\App\Http\Controllers\Api\V1\HealthCheckController::class, 'check']);

    // Public Authentication & Password Reset (Rate limited)
    Route::prefix('auth')->middleware('throttle:60,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    // Verification route named for URL generation
    Route::get('auth/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->name('verification.verify');

    // Protected API Endpoints (Sanctum Authenticated)
    Route::middleware('auth:sanctum')->group(function () {

        // Auth & Profile
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('verify-email', [AuthController::class, 'verifyEmail']);
            Route::post('resend-verification', [AuthController::class, 'resendVerification']);
        });

        Route::prefix('user')->group(function () {
            Route::get('/', [AuthController::class, 'me']);
            Route::put('/profile', [AuthController::class, 'updateProfile']);
            Route::put('/password', [AuthController::class, 'changePassword']);
        });

        // Global Direct Document Upload & AI Analysis
        Route::post('upload', [DocumentController::class, 'directUpload'])->middleware('throttle:60,1');
        Route::post('documents/upload', [DocumentController::class, 'directUpload'])->middleware('throttle:60,1');
        Route::post('documents', [DocumentController::class, 'directUpload'])->middleware('throttle:60,1');
        Route::get('documents/{document}/content', [DocumentController::class, 'content']);
        Route::post('documents/query', [DocumentController::class, 'directQueryDocument'])->middleware('throttle:60,1');
        Route::post('documents/{document}/query', [DocumentController::class, 'queryDocument'])->middleware('throttle:60,1');

        // Global Direct Comparisons (Auto-resolved to user workspace)
        Route::get('comparisons', [ComparisonController::class, 'directIndex']);
        Route::post('comparisons', [ComparisonController::class, 'directStore'])->middleware('throttle:20,1');
        Route::get('comparisons/{comparison}', [ComparisonController::class, 'directShow']);
        Route::get('usage', [UsageController::class, 'directSummary']);

        // First-Class General AI Chatbot & Document Intelligence
        Route::post('chat', [ChatController::class, 'directChat'])->middleware('throttle:60,1');
        Route::post('chat/feedback', [ChatController::class, 'feedback'])->middleware('throttle:60,1');

        // Workspaces
        Route::apiResource('workspaces', WorkspaceController::class)->only(['index', 'store', 'show', 'update']);

        // Workspace Scoped Operations
        Route::prefix('workspaces/{workspace}')->group(function () {
            // Document uploads
            Route::post('documents/upload', [DocumentController::class, 'upload'])->middleware('throttle:30,1');
            Route::get('documents/{document}', [DocumentController::class, 'show']);

            // Comparisons
            Route::get('comparisons', [ComparisonController::class, 'index']);
            Route::post('comparisons', [ComparisonController::class, 'store'])->middleware('throttle:20,1');
            Route::get('comparisons/{comparison}', [ComparisonController::class, 'show']);

            // Usage & Subscription
            Route::get('usage', [UsageController::class, 'summary']);
        });

        // Comparison Job & Status Polling / Streaming / Cancellation
        Route::get('comparisons/{comparison}/status', [ComparisonController::class, 'status'])->middleware('throttle:120,1');
        Route::post('comparisons/{comparison}/cancel', [ComparisonController::class, 'cancel']);

        Route::get('jobs/{job}', [JobController::class, 'show'])->middleware('throttle:60,1');

        // Comparison Reports & Insights
        Route::get('comparisons/{job}/report', [ReportController::class, 'show']);
        Route::get('comparisons/{job}/report/pdf', [ReportController::class, 'downloadPdf']);

        // AI Chat Interface
        Route::prefix('comparisons/{job}/chat')->middleware('throttle:60,1')->group(function () {
            Route::get('/', [ChatController::class, 'index']);
            Route::post('/', [ChatController::class, 'store']);
            Route::delete('/', [ChatController::class, 'clear']);
        });

        // Provider Health & System Status
        Route::get('provider-health', [ProviderHealthController::class, 'index']);

        // Admin-only Provider Management & AI Evaluation Dashboard
        Route::prefix('admin/ai')->group(function () {
            Route::get('/usage-overview', [ProviderManagementController::class, 'usageOverview']);
            Route::get('/evaluation/summary', [\App\Http\Controllers\Api\V1\Admin\AIEvaluationDashboardController::class, 'summary']);
            Route::get('/evaluation/runs', [\App\Http\Controllers\Api\V1\Admin\AIEvaluationDashboardController::class, 'runs']);
            Route::get('/evaluation/failures', [\App\Http\Controllers\Api\V1\Admin\AIEvaluationDashboardController::class, 'failures']);
            Route::post('/evaluation/run', [\App\Http\Controllers\Api\V1\Admin\AIEvaluationDashboardController::class, 'triggerRun']);
        });

        Route::prefix('admin/providers')->group(function () {
            Route::get('/', [ProviderManagementController::class, 'index']);
            Route::get('/fallback-events', [ProviderManagementController::class, 'fallbackEvents']);
            Route::get('/costs', [ProviderManagementController::class, 'costSummary']);
            Route::get('/usage-overview', [ProviderManagementController::class, 'usageOverview']);
            Route::post('/switch-primary', [ProviderManagementController::class, 'switchPrimary']);
            Route::put('/{provider}', [ProviderManagementController::class, 'update']);
            Route::post('/{provider}/ping', [ProviderManagementController::class, 'ping']);
        });

        Route::get('admin/ai/usage-overview', [ProviderManagementController::class, 'usageOverview']);
    });
});

// Root-level aliases for direct /api calls (graceful fallback without v1 prefix)
Route::post('upload', [DocumentController::class, 'directUpload'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::post('documents/upload', [DocumentController::class, 'directUpload'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::post('documents', [DocumentController::class, 'directUpload'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::get('documents/{document}/content', [DocumentController::class, 'content'])->middleware(['auth:sanctum']);
Route::get('health', [\App\Http\Controllers\Api\V1\HealthCheckController::class, 'check']);
Route::post('documents/query', [DocumentController::class, 'directQueryDocument'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::post('chat', [ChatController::class, 'directChat'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::post('chat/feedback', [ChatController::class, 'feedback'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::get('comparisons', [ComparisonController::class, 'directIndex'])->middleware(['auth:sanctum']);
Route::post('comparisons', [ComparisonController::class, 'directStore'])->middleware(['auth:sanctum', 'throttle:20,1']);
Route::get('comparisons/{comparison}', [ComparisonController::class, 'directShow'])->middleware(['auth:sanctum']);
Route::get('comparisons/{comparison}/status', [ComparisonController::class, 'status'])->middleware(['auth:sanctum', 'throttle:120,1']);
Route::post('comparisons/{comparison}/cancel', [ComparisonController::class, 'cancel'])->middleware(['auth:sanctum']);
Route::get('comparisons/{job}/report', [ReportController::class, 'show'])->middleware(['auth:sanctum']);
Route::get('comparisons/{job}/report/pdf', [ReportController::class, 'downloadPdf'])->middleware(['auth:sanctum']);
Route::prefix('comparisons/{job}/chat')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/', [ChatController::class, 'index']);
    Route::post('/', [ChatController::class, 'store']);
    Route::delete('/', [ChatController::class, 'clear']);
});
Route::get('jobs/{job}', [JobController::class, 'show'])->middleware(['auth:sanctum', 'throttle:60,1']);
Route::get('usage', [UsageController::class, 'directSummary'])->middleware(['auth:sanctum']);
Route::get('admin/ai/evaluation/summary', [\App\Http\Controllers\Api\V1\Admin\AIEvaluationDashboardController::class, 'summary'])->middleware(['auth:sanctum']);
Route::get('admin/ai/evaluation/runs', [\App\Http\Controllers\Api\V1\Admin\AIEvaluationDashboardController::class, 'runs'])->middleware(['auth:sanctum']);
Route::get('admin/ai/evaluation/failures', [\App\Http\Controllers\Api\V1\Admin\AIEvaluationDashboardController::class, 'failures'])->middleware(['auth:sanctum']);
Route::post('admin/ai/evaluation/run', [\App\Http\Controllers\Api\V1\Admin\AIEvaluationDashboardController::class, 'triggerRun'])->middleware(['auth:sanctum']);
