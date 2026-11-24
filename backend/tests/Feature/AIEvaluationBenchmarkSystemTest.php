<?php

namespace Tests\Feature;

use App\Models\AIEvaluationResult;
use App\Models\AIEvaluationRun;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\Intent\TaskIntentDTO;
use App\Services\AI\Evaluation\AIEvaluationEngine;
use App\Services\AI\Validation\ResponseValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AIEvaluationBenchmarkSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->adminUser = User::factory()->create([
            'email' => 'admin_eval_test@docver.com',
            'role' => \App\Enums\UserRole::SUPER_ADMIN,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Benchmark Test Workspace',
            'slug' => 'eval-test-ws-' . uniqid(),
            'owner_id' => $this->adminUser->id,
            'plan' => 'enterprise',
            'max_file_size_mb' => 50,
        ]);
    }

    /**
     * TEST 1: The AI evaluation dataset contains exactly 1,150 test cases across 10 categories.
     */
    public function test_ai_evaluation_dataset_contains_all_1150_test_cases(): void
    {
        $dir = database_path('ai_evaluation/dataset');
        $this->assertDirectoryExists($dir);

        $expectedCounts = [
            'general_conversation.json' => 200,
            'nl_spelling_variations.json' => 200,
            'document_qa.json' => 150,
            'document_summarization.json' => 100,
            'document_comparison.json' => 100,
            'structured_extraction.json' => 100,
            'multi_turn_conversation.json' => 50,
            'hallucination_prevention.json' => 100,
            'upload_processing_failure.json' => 50,
            'output_format_instruction_following.json' => 100,
        ];

        $totalFound = 0;
        foreach ($expectedCounts as $file => $expectedCount) {
            $path = "{$dir}/{$file}";
            $this->assertFileExists($path, "Dataset file {$file} is missing.");
            $content = json_decode(file_get_contents($path), true);
            $this->assertIsArray($content, "File {$file} does not contain valid JSON array.");
            $this->assertCount($expectedCount, $content, "File {$file} does not match expected count of {$expectedCount}.");

            // Verify each item contains required fields
            foreach (array_slice($content, 0, 5) as $item) {
                $this->assertArrayHasKey('id', $item);
                $this->assertArrayHasKey('category', $item);
                $this->assertArrayHasKey('input', $item);
                $this->assertArrayHasKey('expected_task', $item);
                $this->assertArrayHasKey('expected_answer', $item);
                $this->assertArrayHasKey('evaluation_method', $item);
                $this->assertArrayHasKey('failure_category', $item);
            }

            $totalFound += count($content);
        }

        $this->assertEquals(1150, $totalFound, "Total dataset count must equal exactly 1,150 test cases.");
    }

    /**
     * TEST 2: Strict instruction following enforces only names, tables, and numerical metrics.
     */
    public function test_strict_instruction_following_enforces_exact_formats(): void
    {
        $validator = app(ResponseValidator::class);

        // Case A: User: "Give me only the names."
        $intentNames = new TaskIntentDTO(
            intent: 'key_entities',
            rawQuery: 'Give me only the names.',
            constraints: ['only_names', 'forbid_recommendations']
        );
        $verboseNames = "Here are the team members:\n- Nihal Cilamkoti\n- Priya Sharma\n- Alex Mercer\n\nRecommendations:\nWe suggest scheduling a sync.";
        $cleanNames = $validator->applyConstraintPostProcessing($verboseNames, $intentNames);
        $this->assertStringContainsString('Nihal Cilamkoti', $cleanNames);
        $this->assertStringContainsString('Priya Sharma', $cleanNames);
        $this->assertStringNotContainsString('Recommendations', $cleanNames);
        $this->assertStringNotContainsString('Here are the team members', $cleanNames);

        // Case B: User: "Show the differences in a table."
        $intentTable = new TaskIntentDTO(
            intent: 'comparison',
            rawQuery: 'Show the differences in a table.',
            constraints: ['format_markdown_table']
        );
        $tableContent = "Certainly! Below is the comparison table you requested:\n\n| Provision | 2024 | 2025 |\n|---|---|---|\n| Payment | Net 30 | Net 60 |\n| Liability | $500k | $1M |\n\nLet me know if you need anything else!";
        $cleanTable = $validator->applyConstraintPostProcessing($tableContent, $intentTable);
        $this->assertStringStartsWith('| Provision | 2024 | 2025 |', $cleanTable);
        $this->assertStringNotContainsString('Certainly!', $cleanTable);
        $this->assertStringNotContainsString('Let me know if you need anything else!', $cleanTable);

        // Case C: User: "Give me only numerical differences."
        $intentNum = new TaskIntentDTO(
            intent: 'comparison',
            rawQuery: 'Give me only numerical differences.',
            constraints: ['only_numerical']
        );
        $mixedContent = "Document A has a liability cap of $500,000 USD.\nSection 8 is governed by Delaware law.\nDocument B has a liability cap of $1,000,000 USD.\nJonathan Miller signed Document A.";
        $cleanNum = $validator->applyConstraintPostProcessing($mixedContent, $intentNum);
        $this->assertStringContainsString('$500,000', $cleanNum);
        $this->assertStringContainsString('$1,000,000', $cleanNum);
        $this->assertStringNotContainsString('Delaware law', $cleanNum);
        $this->assertStringNotContainsString('Jonathan Miller signed', $cleanNum);

        // Case D: User: "Make it shorter."
        $intentShort = new TaskIntentDTO(
            intent: 'summarization',
            rawQuery: 'Make it shorter.',
            constraints: ['make_shorter']
        );
        $longContent = "Sentence one explains the terms. Sentence two describes the payment schedule. Sentence three covers indemnification. Sentence four details termination. Sentence five describes arbitration. Sentence six lists governing law.";
        $cleanShort = $validator->applyConstraintPostProcessing($longContent, $intentShort);
        $this->assertLessThan(strlen($longContent), strlen($cleanShort));
        $this->assertStringContainsString('Sentence one explains the terms.', $cleanShort);
    }

    /**
     * TEST 3: Evaluation Engine benchmark execution produces genuine metrics in database.
     */
    public function test_evaluation_engine_benchmark_execution_and_reporting(): void
    {
        $engine = app(AIEvaluationEngine::class);

        // Run benchmark on a small sample of general conversation and upload failures
        $run = $engine->runBenchmark(category: 'upload_processing_failure', provider: 'groq', limit: 5);

        $this->assertInstanceOf(AIEvaluationRun::class, $run);
        $this->assertDatabaseHas('ai_evaluation_runs', ['run_id' => $run->run_id]);
        $this->assertEquals(5, $run->total_tests);
        $this->assertGreaterThanOrEqual(1, $run->passed_tests);
        $this->assertGreaterThan(0, $run->duration_ms);

        $results = AIEvaluationResult::where('run_id', $run->run_id)->get();
        $this->assertCount(5, $results);

        foreach ($results as $res) {
            $this->assertEquals('upload_processing_failure', $res->category);
            $this->assertNotEmpty($res->input);
        }
    }

    /**
     * TEST 4: Admin AI Evaluation Dashboard API endpoint returns complete analytics.
     */
    public function test_admin_evaluation_dashboard_api_summary_endpoint(): void
    {
        // Seed a sample evaluation run
        $run = AIEvaluationRun::create([
            'run_id' => 'run_test_api_summary',
            'provider' => 'groq',
            'model' => 'openai/gpt-oss-20b',
            'category' => 'all',
            'total_tests' => 100,
            'passed_tests' => 97,
            'failed_tests' => 3,
            'overall_accuracy' => 97.0,
            'instruction_accuracy' => 98.5,
            'citation_accuracy' => 96.0,
            'grounding_accuracy' => 99.0,
            'hallucination_rate' => 1.0,
            'avg_latency_ms' => 135,
            'total_tokens' => 45000,
            'duration_ms' => 15200,
            'status' => 'completed',
            'metrics' => [
                'category_stats' => [
                    'general_conversation' => ['total' => 10, 'passed' => 10, 'failed' => 0],
                    'document_qa' => ['total' => 10, 'passed' => 9, 'failed' => 1],
                ],
                'failure_categories' => [
                    'fact_retrieval_error' => 1,
                ],
            ],
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/admin/ai/evaluation/summary');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotNull($data);
        $this->assertEquals(100, $data['latest_run']['total_tests']);
        $this->assertEquals(97.0, $data['latest_run']['overall_accuracy']);
        $this->assertArrayHasKey('task_wise_accuracy', $data);
        $this->assertArrayHasKey('provider_comparison', $data);
        $this->assertArrayHasKey('common_failure_categories', $data);
        $this->assertArrayHasKey('historical_runs', $data);

        // Verify multi-provider comparison includes Groq, Gemini, and OpenRouter
        $providers = collect($data['provider_comparison'])->pluck('provider')->toArray();
        $this->assertContains('groq', $providers);
        $this->assertContains('gemini', $providers);
        $this->assertContains('openrouter', $providers);
    }
}
