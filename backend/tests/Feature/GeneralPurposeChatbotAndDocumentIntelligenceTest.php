<?php

namespace Tests\Feature;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIOrchestrator;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralPurposeChatbotAndDocumentIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected AIOrchestrator $orchestrator;
    protected EphemeralIndexManager $indexManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'auditor@docver.ai',
            'name' => 'DocVer Auditor',
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Default Workspace',
            'slug' => 'default-ws',
            'owner_id' => $this->user->id,
            'plan' => 'enterprise',
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);

        $this->orchestrator = app(AIOrchestrator::class);
        $this->indexManager = app(EphemeralIndexManager::class);
    }

    /**
     * TEST 1: General Chatbot - "Where is India?"
     */
    public function test_01_general_chatbot_where_is_india(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'Where is India?',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.ai_response.content');

        $this->assertNotEmpty($data);
        $this->assertTrue(
            str_contains(strtolower($data), 'south asia') || str_contains(strtolower($data), 'asia') || str_contains(strtolower($data), 'country in'),
            "Response should describe India's geographical location: {$data}"
        );
        $this->assertStringNotContainsStringIgnoringCase('Document A', $data);
        $this->assertStringNotContainsStringIgnoringCase('Document B', $data);
        $this->assertStringNotContainsStringIgnoringCase('liability cap', $data);
    }

    /**
     * TEST 2: General Chatbot - "What is artificial intelligence?"
     */
    public function test_02_general_chatbot_what_is_artificial_intelligence(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'What is artificial intelligence?',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.ai_response.content');

        $this->assertNotEmpty($data);
        $this->assertTrue(
            str_contains(strtolower($data), 'intelligence') || str_contains(strtolower($data), 'computer') || str_contains(strtolower($data), 'machine'),
            "Response should explain artificial intelligence: {$data}"
        );
        $this->assertStringNotContainsStringIgnoringCase('please attach', $data);
        $this->assertStringNotContainsStringIgnoringCase('Document A', $data);
    }

    /**
     * TEST 3: General Chatbot - "Write an email to my professor."
     */
    public function test_03_general_chatbot_write_email_to_professor(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'Write an email to my professor asking for an extension on my project.',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.ai_response.content');

        $this->assertNotEmpty($data);
        $this->assertTrue(
            str_contains(strtolower($data), 'subject') || str_contains(strtolower($data), 'dear professor') || str_contains(strtolower($data), 'extension'),
            "Response should format a professional email draft: {$data}"
        );
    }

    /**
     * TEST 4: General Chatbot - "Explain Python loops."
     */
    public function test_04_general_chatbot_explain_python_loops(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'Explain Python loops with a simple example.',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.ai_response.content');

        $this->assertNotEmpty($data);
        $this->assertTrue(
            str_contains($data, 'for ') || str_contains($data, 'while ') || str_contains(strtolower($data), 'loop'),
            "Response should explain Python loops with code examples: {$data}"
        );
    }

    /**
     * TEST 5: General Chatbot - "Give me five project ideas."
     */
    public function test_05_general_chatbot_give_five_project_ideas(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'Give me five project ideas for web development.',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.ai_response.content');

        $this->assertNotEmpty($data);
        $this->assertTrue(
            str_contains($data, '1.') || str_contains($data, '1)') || count(explode("\n", trim($data))) >= 4,
            "Response should list project ideas: {$data}"
        );
    }

    /**
     * TEST 6: Document Understanding - Summary & Specific Question
     */
    public function test_06_document_understanding_summary_and_specific_qa(): void
    {
        $doc = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'Project_Plan.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'page_count' => 2,
            'storage_path' => 'documents/plan.pdf',
            'expires_at' => now()->addDays(7),
        ]);

        $result = $this->orchestrator->answerDocumentQuery($doc, 'Give me a summary of this document', $this->user->id);

        $this->assertNotEmpty($result['answer']);
        $this->assertSame('Project_Plan.pdf', $result['document_name']);
    }

    /**
     * TEST 7: Document Understanding - Extract Only Names
     */
    public function test_07_document_understanding_extract_only_names(): void
    {
        $doc = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'Team_Roster.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'page_count' => 1,
            'storage_path' => 'documents/roster.pdf',
            'expires_at' => now()->addDays(7),
        ]);

        $result = $this->orchestrator->answerDocumentQuery($doc, 'Extract only the names', $this->user->id);

        $this->assertNotEmpty($result['answer']);
        $this->assertStringNotContainsStringIgnoringCase('Executive Summary', $result['answer']);
        $this->assertStringNotContainsStringIgnoringCase('Recommendations:', $result['answer']);
    }

    /**
     * TEST 8: Document Understanding - Missing Information
     */
    public function test_08_document_understanding_missing_information(): void
    {
        $doc = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'Office_Guidelines.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'page_count' => 1,
            'storage_path' => 'documents/guidelines.pdf',
            'expires_at' => now()->addDays(7),
        ]);

        $result = $this->orchestrator->answerDocumentQuery($doc, 'What is the penalty for interplanetary space travel in this document?', $this->user->id);

        $this->assertNotEmpty($result['answer']);
        $this->assertTrue(
            str_contains(strtolower($result['answer']), 'does not contain') || str_contains(strtolower($result['answer']), 'not found') || str_contains(strtolower($result['answer']), 'not mention'),
            "AI should honestly state when requested information is absent: {$result['answer']}"
        );
    }

    /**
     * TEST 9: Document Comparison - "Tell me the difference between these two documents"
     */
    public function test_09_document_comparison_tell_me_the_difference(): void
    {
        $docA = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'Schedule.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'page_count' => 1,
            'storage_path' => 'documents/schedule.pdf',
            'expires_at' => now()->addDays(7),
        ]);

        $docB = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'B',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'roles assigned.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'page_count' => 2,
            'storage_path' => 'documents/roles.pdf',
            'expires_at' => now()->addDays(7),
        ]);

        $job = ComparisonJob::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'document_a_id' => $docA->id,
            'document_b_id' => $docB->id,
            'status' => 'completed',
            'title' => 'Schedule.pdf vs roles assigned.pdf',
        ]);

        $this->indexManager->storeChunks($job->id, 'A', [
            ['id' => 'c1', 'section_title' => 'Daily Schedule', 'content' => 'Daily Schedule: Wake up at 6:00 AM, breakfast at 7:00 AM, deep work from 8:00 AM to 12:00 PM.', 'page_number' => 1],
        ]);

        $this->indexManager->storeChunks($job->id, 'B', [
            ['id' => 'c2', 'section_title' => 'Assigned Roles', 'content' => 'Assigned Roles: Nihal is Lead Architect handling backend and AI models. Sarah handles frontend design.', 'page_number' => 1],
        ]);

        $result = $this->orchestrator->answerComparisonQuery($job, 'Tell me the difference between these two documents', $this->user->id);

        $this->assertNotEmpty($result['content']);
        $this->assertStringNotContainsStringIgnoringCase('High-risk legal breach', $result['content']);
        $this->assertStringNotContainsStringIgnoringCase('Indemnification liability', $result['content']);
    }

    /**
     * TEST 10: Document Comparison - "Compare them in a table"
     */
    public function test_10_document_comparison_compare_in_a_table(): void
    {
        $docA = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'Version1.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => 500,
            'page_count' => 1,
            'storage_path' => 'documents/v1.txt',
            'expires_at' => now()->addDays(7),
        ]);

        $docB = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'B',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'Version2.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => 500,
            'page_count' => 1,
            'storage_path' => 'documents/v2.txt',
            'expires_at' => now()->addDays(7),
        ]);

        $job = ComparisonJob::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'document_a_id' => $docA->id,
            'document_b_id' => $docB->id,
            'status' => 'completed',
            'title' => 'Version1.txt vs Version2.txt',
        ]);

        $this->indexManager->storeChunks($job->id, 'A', [
            ['id' => 'c1', 'section_title' => 'Pricing', 'content' => 'Monthly fee is $100.', 'page_number' => 1],
        ]);

        $this->indexManager->storeChunks($job->id, 'B', [
            ['id' => 'c2', 'section_title' => 'Pricing', 'content' => 'Monthly fee is $150.', 'page_number' => 1],
        ]);

        $result = $this->orchestrator->answerComparisonQuery($job, 'Compare them in a table', $this->user->id);

        $this->assertNotEmpty($result['content']);
        $this->assertTrue(
            str_contains($result['content'], '|') && str_contains($result['content'], '---'),
            "Response should contain a markdown table when requested: {$result['content']}"
        );
    }

    /**
     * TEST 11: Document Comparison - "Compare only the responsibilities"
     */
    public function test_11_document_comparison_compare_only_responsibilities(): void
    {
        $docA = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'TeamA.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => 500,
            'page_count' => 1,
            'storage_path' => 'documents/teamA.txt',
            'expires_at' => now()->addDays(7),
        ]);

        $docB = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'B',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'TeamB.txt',
            'mime_type' => 'text/plain',
            'file_size_bytes' => 500,
            'page_count' => 1,
            'storage_path' => 'documents/teamB.txt',
            'expires_at' => now()->addDays(7),
        ]);

        $job = ComparisonJob::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'document_a_id' => $docA->id,
            'document_b_id' => $docB->id,
            'status' => 'completed',
            'title' => 'TeamA.txt vs TeamB.txt',
        ]);

        $this->indexManager->storeChunks($job->id, 'A', [
            ['id' => 'c1', 'section_title' => 'Roles', 'content' => 'Team A: Alex manages cloud deployment and database.', 'page_number' => 1],
        ]);

        $this->indexManager->storeChunks($job->id, 'B', [
            ['id' => 'c2', 'section_title' => 'Roles', 'content' => 'Team B: Jordan manages mobile app and testing.', 'page_number' => 1],
        ]);

        $result = $this->orchestrator->answerComparisonQuery($job, 'Compare only the responsibilities', $this->user->id);

        $this->assertNotEmpty($result['content']);
        $this->assertStringNotContainsStringIgnoringCase('Executive Summary', $result['content']);
        $this->assertStringNotContainsStringIgnoringCase('Legal Disclaimer', $result['content']);
    }

    /**
     * TEST 12: Document Relationship - "Are these different versions of the same document?"
     */
    public function test_12_document_relationship_check(): void
    {
        $docA = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'A',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'Schedule.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'page_count' => 1,
            'storage_path' => 'documents/schedule.pdf',
            'expires_at' => now()->addDays(7),
        ]);

        $docB = DocumentMetadata::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'slot' => 'B',
            'checksum_sha256' => hash('sha256', 'dummy_hash_' . uniqid()),
            'file_size' => 1024,
            'original_name' => 'roles assigned.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'page_count' => 2,
            'storage_path' => 'documents/roles.pdf',
            'expires_at' => now()->addDays(7),
        ]);

        $job = ComparisonJob::create([
            'user_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'document_a_id' => $docA->id,
            'document_b_id' => $docB->id,
            'status' => 'completed',
            'title' => 'Schedule.pdf vs roles assigned.pdf',
        ]);

        $this->indexManager->storeChunks($job->id, 'A', [
            ['id' => 'c1', 'section_title' => 'Daily Schedule', 'content' => 'Daily Schedule: Wake up at 6:00 AM, breakfast at 7:00 AM, work 8:00 AM to 5:00 PM.', 'page_number' => 1],
        ]);

        $this->indexManager->storeChunks($job->id, 'B', [
            ['id' => 'c2', 'section_title' => 'Assigned Roles', 'content' => 'Assigned Roles: Nihal is Lead Architect, Sarah is Frontend Lead.', 'page_number' => 1],
        ]);

        $result = $this->orchestrator->answerComparisonQuery($job, 'Are these different versions of the same document?', $this->user->id);

        $this->assertNotEmpty($result['content']);
        $this->assertTrue(
            str_contains(strtolower($result['content']), 'different purposes') ||
            str_contains(strtolower($result['content']), 'not versions') ||
            str_contains(strtolower($result['content']), 'distinct') ||
            str_contains(strtolower($result['content']), 'independent') ||
            str_contains(strtolower($result['content']), 'no'),
            "AI should clearly recognize that Schedule and Roles are different documents with different purposes: {$result['content']}"
        );
    }

    /**
     * TEST 13: Direct Chat without Documents - "Help me solve a problem"
     */
    public function test_13_direct_chat_without_documents(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'Help me solve 2x + 8 = 20.',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data.ai_response.content');

        $this->assertNotEmpty($data);
        $this->assertTrue(
            str_contains($data, '6') || str_contains($data, 'x = 6'),
            "Response should correctly solve the math problem: {$data}"
        );
    }
}
