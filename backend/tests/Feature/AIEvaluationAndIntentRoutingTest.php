<?php

namespace Tests\Feature;

use App\Models\AIEvaluationLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\Evaluation\AIEvaluationLogger;
use App\Services\AI\Intent\TaskIntentClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIEvaluationAndIntentRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'auditor@docver.com']);
        $this->workspace = Workspace::create([
            'name' => 'Primary Test Workspace',
            'slug' => 'primary-test-ws-' . uniqid(),
            'owner_id' => $this->user->id,
            'plan' => 'professional',
            'status' => 'active',
            'max_file_size_mb' => 100,
            'max_page_limit' => 2000,
        ]);
        $this->workspace->members()->attach($this->user->id, ['role' => 'owner']);
    }

    /**
     * Test intent classification for document drafting, rewriting, and external research.
     */
    public function test_advanced_intents_are_classified_accurately(): void
    {
        $classifier = app(TaskIntentClassifier::class);

        $createNda = $classifier->classify('Create a mutual non-disclosure agreement for two tech startups');
        $this->assertEquals('document_creation', $createNda->intent);

        $rewrite = $classifier->classify('Rewrite this clause to simplify the language and improve grammar');
        $this->assertEquals('document_rewrite', $rewrite->intent);

        $research = $classifier->classify('Research online and search the web for standard SaaS payment terms');
        $this->assertEquals('external_research', $research->intent);
    }

    /**
     * Test AI interaction logging and continuous evaluation tracking.
     */
    public function test_ai_interaction_is_logged_to_evaluation_table(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'Where is India?',
        ]);

        $response->assertStatus(200);
        $aiResp = $response->json('data.ai_response');

        $this->assertNotEmpty($aiResp['content']);
        $this->assertNotNull($aiResp['log_id']);

        $log = AIEvaluationLog::find($aiResp['log_id']);
        $this->assertNotNull($log);
        $this->assertEquals('general_chat', $log->task_type);
        $this->assertEquals('Where is India?', $log->input_prompt);
        $this->assertTrue($log->is_successful);
        $this->assertEquals('v2.4.0', $log->prompt_version);
    }

    /**
     * Test recording user thumbs up/down feedback for offline model/prompt evaluation.
     */
    public function test_user_can_submit_thumbs_down_feedback(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'What is artificial intelligence?',
        ]);

        $logId = $response->json('data.ai_response.log_id');
        $this->assertNotNull($logId);

        $fbResponse = $this->actingAs($this->user)->postJson('/api/v1/chat/feedback', [
            'log_id' => $logId,
            'rating' => 'down',
            'feedback' => 'Needs a more concrete real-world example',
        ]);

        $fbResponse->assertStatus(200);

        $log = AIEvaluationLog::find($logId);
        $this->assertEquals('down', $log->user_rating);
        $this->assertEquals('Needs a more concrete real-world example', $log->user_feedback);
        $this->assertFalse($log->is_successful);
    }
}
