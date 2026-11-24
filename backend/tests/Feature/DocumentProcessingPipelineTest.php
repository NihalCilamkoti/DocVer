<?php

namespace Tests\Feature;

use App\Enums\ComparisonStatus;
use App\Enums\UserRole;
use App\Jobs\ValidateUploadedFileJob;
use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use App\Services\Documents\Pipeline\DocumentPipelineCoordinator;
use App\Services\Documents\Pipeline\PipelineProgressTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentProcessingPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->user = User::create([
            'name' => 'Auditor User',
            'email' => 'pipeline_auditor@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::USER,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Compliance Workspace',
            'slug' => 'compliance-ws',
            'owner_id' => $this->user->id,
            'plan' => 'enterprise',
            'max_file_size_mb' => 50,
        ]);

        $this->workspace->members()->attach($this->user->id, ['role' => 'admin']);
        $this->token = $this->user->createToken('pipeline-token')->plainTextToken;
    }

    public function test_direct_upload_endpoint_accepts_valid_documents(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'MasterServiceAgreement.txt',
            "Article 1: Scope of Work.\nParty A agrees to deliver software.\nArticle 2: Payment.\nFee is $100,000."
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/upload', [
                'file' => $file,
                'slot' => 'A',
                'workspace_id' => $this->workspace->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'MasterServiceAgreement.txt')
            ->assertJsonPath('data.slot', 'A');
    }

    public function test_upload_rejects_executable_disguised_as_pdf(): void
    {
        $malicious = UploadedFile::fake()->createWithContent(
            'Trojan.pdf',
            "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF\x00\x00"
        );

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/upload', [
                'file' => $malicious,
                'slot' => 'A',
                'workspace_id' => $this->workspace->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['file']]);
    }

    public function test_upload_prevents_duplicate_file_in_same_comparison_job(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Duplicate Test Job',
            'status' => ComparisonStatus::QUEUED,
        ]);

        $identicalContent = "Article 1.0 Non-Disclosure and Confidentiality Obligations.";
        $file1 = UploadedFile::fake()->createWithContent('DocA.txt', $identicalContent);
        $file2 = UploadedFile::fake()->createWithContent('DocB.txt', $identicalContent);

        // Upload Slot A
        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/upload', [
                'file' => $file1,
                'slot' => 'A',
                'comparison_job_id' => $job->id,
                'workspace_id' => $this->workspace->id,
            ])
            ->assertStatus(201);

        // Upload Slot B with identical content (same hash in same job)
        $response2 = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/v1/upload', [
                'file' => $file2,
                'slot' => 'B',
                'comparison_job_id' => $job->id,
                'workspace_id' => $this->workspace->id,
            ]);

        $response2->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_status_endpoint_returns_progress_and_stage(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Audit Job 1',
            'status' => ComparisonStatus::PROCESSING,
            'processing_progress' => 45,
            'current_stage' => 'detecting_scans',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/v1/comparisons/{$job->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.job_id', $job->id)
            ->assertJsonPath('data.progress', 45)
            ->assertJsonPath('data.stage', 'detecting_scans');
    }

    public function test_status_endpoint_supports_sse_streaming(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'SSE Streaming Job',
            'status' => ComparisonStatus::PROCESSING,
            'processing_progress' => 50,
            'current_stage' => 'ocr_processing',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->withHeader('Accept', 'text/event-stream')
            ->get("/api/v1/comparisons/{$job->id}/status");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');
    }

    public function test_cancel_endpoint_halts_pipeline_and_sets_cancelled_state(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Cancellable Job',
            'status' => ComparisonStatus::PROCESSING,
            'processing_progress' => 30,
            'current_stage' => 'extracting',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/comparisons/{$job->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');

        $job->refresh();
        $this->assertEquals(ComparisonStatus::CANCELLED, $job->status);
        $this->assertNotNull($job->cancelled_at);

        $tracker = app(PipelineProgressTracker::class);
        $this->assertTrue($tracker->isCancelled($job->id));
    }

    public function test_document_pipeline_coordinator_executes_all_9_stages(): void
    {
        $contentA = "Article 1.0 General Provisions\nContractor shall deliver widgets in accordance with Exhibit A.\n\nArticle 2.0 Invoicing\nPayment shall occur within 30 days.";
        $contentB = "Article 1.0 General Provisions\nContractor shall deliver widgets in accordance with Exhibit B.\n\nArticle 2.0 Invoicing\nPayment shall occur within 60 days.";

        $pathA = 'documents/test_a.txt';
        $pathB = 'documents/test_b.txt';
        Storage::disk('local')->put($pathA, $contentA);
        Storage::disk('local')->put($pathB, $contentB);

        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'End-to-End Pipeline Test',
            'status' => ComparisonStatus::QUEUED,
        ]);

        DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'OriginalAgreement.txt',
            'storage_path' => $pathA,
            'mime_type' => 'text/plain',
            'file_size' => strlen($contentA),
            'checksum_sha256' => hash('sha256', $contentA),
            'expires_at' => now()->addDays(30),
        ]);

        DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'RevisedAgreement.txt',
            'storage_path' => $pathB,
            'mime_type' => 'text/plain',
            'file_size' => strlen($contentB),
            'checksum_sha256' => hash('sha256', $contentB),
            'expires_at' => now()->addDays(30),
        ]);

        $coordinator = app(DocumentPipelineCoordinator::class);
        $coordinator->runSynchronously($job);

        // Verify indexing succeeded
        $indexManager = app(EphemeralIndexManager::class);
        $this->assertTrue($indexManager->hasIndex($job->id));

        $chunksA = $indexManager->getChunks($job->id, 'A');
        $chunksB = $indexManager->getChunks($job->id, 'B');

        $this->assertNotEmpty($chunksA);
        $this->assertNotEmpty($chunksB);
        $this->assertEquals('A', $chunksA[0]['slot']);
        $this->assertEquals('B', $chunksB[0]['slot']);
        $this->assertEquals(1, $chunksA[0]['page_number']);

        // Verify progress is 100% completed
        $tracker = app(PipelineProgressTracker::class);
        $progress = $tracker->getProgress($job->id);
        $this->assertEquals(100, $progress['progress']);
        $this->assertEquals('completed', $progress['stage']);

        // Verify zero permanent raw file storage (files purged after indexing)
        $this->assertFalse(Storage::disk('local')->exists($pathA));
        $this->assertFalse(Storage::disk('local')->exists($pathB));
    }
}
