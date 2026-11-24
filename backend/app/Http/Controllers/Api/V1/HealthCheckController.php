<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\AI\Health\CircuitBreaker;
use App\Services\AI\Router\AIProviderRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthCheckController extends BaseApiController
{
    public function check(AIProviderRouter $aiRouter, CircuitBreaker $circuitBreaker): JsonResponse
    {
        $status = 'healthy';
        $checks = [];

        // 1. Database Connection
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'up', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $checks['database'] = ['status' => 'down', 'message' => $e->getMessage()];
        }

        // 2. Cache / Redis Connection
        try {
            Cache::put('docver:healthcheck:ping', true, 10);
            $cached = Cache::get('docver:healthcheck:ping');
            $checks['cache'] = ['status' => $cached ? 'up' : 'down'];
        } catch (\Throwable $e) {
            $status = 'unhealthy';
            $checks['cache'] = ['status' => 'down', 'message' => $e->getMessage()];
        }

        // 3. Storage
        try {
            $testFile = 'healthcheck_' . time() . '.tmp';
            Storage::disk('local')->put($testFile, 'ok');
            Storage::disk('local')->delete($testFile);
            $checks['storage'] = ['status' => 'up'];
        } catch (\Throwable $e) {
            $status = 'degraded';
            $checks['storage'] = ['status' => 'down', 'message' => $e->getMessage()];
        }

        // 4. AI Providers Circuit Breaker Summary
        $providersHealth = [];
        foreach ($aiRouter->getProviders() as $name => $provider) {
            $cbState = $circuitBreaker->getState($name);
            $providersHealth[$name] = [
                'configured' => $provider->isConfigured(),
                'circuit_breaker' => $cbState,
            ];
        }
        $checks['ai_providers'] = $providersHealth;

        // 5. Dedicated Actual Model Generation Test (Verifies live LLM inference, not just HTTP connectivity)
        if (request()->boolean('test_generation') || request()->has('test_generation')) {
            $genStart = microtime(true);
            try {
                $testPrompt = 'Write a two-sentence summary of the concept of document verification.';
                $testReq = new \App\Services\AI\DTOs\AIRequest(
                    prompt: $testPrompt,
                    systemInstruction: 'You are an AI document verification expert. Provide a concise, professional 2-sentence response.',
                    temperature: 0.1,
                    maxTokens: 150
                );
                $genRes = $aiRouter->route($testReq);
                $checks['generation_test'] = [
                    'status' => 'passed',
                    'provider' => $genRes->provider,
                    'model' => $genRes->model,
                    'prompt' => $testPrompt,
                    'generated_text' => trim($genRes->content),
                    'latency_ms' => (int) round((microtime(true) - $genStart) * 1000),
                ];
            } catch (\Throwable $e) {
                $checks['generation_test'] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $httpCode = ($status === 'unhealthy') ? 503 : 200;

        return response()->json([
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'app_version' => config('app.version', '1.0.0'),
            'checks' => $checks,
        ], $httpCode);
    }
}
