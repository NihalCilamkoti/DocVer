<?php

namespace Database\Seeders;

use App\Models\ProviderHealth;
use Illuminate\Database\Seeder;

class ProviderHealthSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'provider_name' => 'openai',
                'status' => 'healthy',
                'latency_ms' => 240,
                'last_checked_at' => now(),
                'error_details' => null,
                'metadata' => ['model' => 'gpt-4o', 'region' => 'us-east-1'],
            ],
            [
                'provider_name' => 'anthropic',
                'status' => 'healthy',
                'latency_ms' => 310,
                'last_checked_at' => now(),
                'error_details' => null,
                'metadata' => ['model' => 'claude-3-7-sonnet', 'region' => 'us-east-1'],
            ],
            [
                'provider_name' => 'gemini',
                'status' => 'healthy',
                'latency_ms' => 180,
                'last_checked_at' => now(),
                'error_details' => null,
                'metadata' => ['model' => 'gemini-1.5-pro', 'region' => 'us-central1'],
            ],
            [
                'provider_name' => 'ocr_engine',
                'status' => 'healthy',
                'latency_ms' => 85,
                'last_checked_at' => now(),
                'error_details' => null,
                'metadata' => ['engine' => 'tesseract-v5-pdfium', 'threads' => 8],
            ],
        ];

        foreach ($providers as $prov) {
            ProviderHealth::updateOrCreate(
                ['provider_name' => $prov['provider_name']],
                $prov
            );
        }
    }
}
