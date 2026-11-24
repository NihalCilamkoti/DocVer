<?php

namespace App\Providers;

use App\Models\ComparisonJob;
use App\Models\ComparisonReport;
use App\Models\Workspace;
use App\Policies\ComparisonJobPolicy;
use App\Policies\ReportPolicy;
use App\Policies\WorkspacePolicy;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\OrchestratedAIService;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\Documents\Contracts\MalwareScannerInterface;
use App\Services\Documents\Contracts\OCRServiceInterface;
use App\Services\Documents\OCR\OCRService;
use App\Services\Documents\Security\NullMalwareScanner;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind multi-provider AI orchestrator
        $this->app->singleton(AIProviderRouter::class);
        $this->app->singleton(AIOrchestrator::class);
        $this->app->bind(AIServiceInterface::class, OrchestratedAIService::class);

        // Document engine contracts
        $this->app->bind(OCRServiceInterface::class, OCRService::class);
        $this->app->bind(MalwareScannerInterface::class, NullMalwareScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Policies
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(ComparisonJob::class, ComparisonJobPolicy::class);
        Gate::policy(ComparisonReport::class, ReportPolicy::class);
    }
}
