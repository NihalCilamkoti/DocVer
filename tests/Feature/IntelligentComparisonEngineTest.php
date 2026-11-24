<?php

namespace Tests\Feature;

use App\Enums\ComparisonStatus;
use App\Enums\UserRole;
use App\Models\ComparisonJob;
use App\Models\ComparisonReport;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Comparison\ComparisonOrchestrator;
use App\Services\Documents\DTOs\DocumentChunkDTO;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligentComparisonEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected ComparisonJob $job;
    protected string $token;
    protected EphemeralIndexManager $indexManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Auditor Chief',
            'email' => 'chief_auditor@docver.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::WORKSPACE_ADMIN,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Enterprise Legal Workspace',
            'slug' => 'legal-ws',
            'owner_id' => $this->user->id,
            'plan' => 'enterprise',
            'max_file_size_mb' => 50,
        ]);

        $this->workspace->members()->attach($this->user->id, ['role' => 'admin']);
        $this->token = $this->user->createToken('auditor-token')->plainTextToken;

        $this->job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Master Services Agreement v1 vs v2',
            'status' => ComparisonStatus::PROCESSING,
        ]);

        $this->indexManager = app(EphemeralIndexManager::class);
    }

    public function test_intelligent_comparison_orchestrator_detects_controlled_differences(): void
    {
        // 1. Prepare Document A chunks with known clauses
        $chunksA = [
            new DocumentChunkDTO(
                chunkId: 'doc_a_c1', documentSlot: 'A', documentId: 1, pageNumber: 7,
                sectionTitle: 'Section 4.0 Payment Terms',
                content: "Section 4.0 Payment Terms\nAll invoices shall be settled Net 60 days following acceptance.",
                startChar: 0, endChar: 80
            ),
            new DocumentChunkDTO(
                chunkId: 'doc_a_c2', documentSlot: 'A', documentId: 1, pageNumber: 18,
                sectionTitle: 'Section 14.0 Indemnification and Liability',
                content: "Section 14.0 Indemnification and Liability\nVendor shall indemnify client. Aggregate liability is capped at \$2,000,000 USD.",
                startChar: 0, endChar: 120
            ),
            new DocumentChunkDTO(
                chunkId: 'doc_a_c3', documentSlot: 'A', documentId: 1, pageNumber: 26,
                sectionTitle: 'Section 19.0 Work-for-Hire IP Assignment',
                content: "Section 19.0 Work-for-Hire IP Assignment\nAll deliverables are work-for-hire and belong exclusively to the client.",
                startChar: 0, endChar: 110
            ),
        ];

        // 2. Prepare Document B chunks with modified terms
        $chunksB = [
            new DocumentChunkDTO(
                chunkId: 'doc_b_c1', documentSlot: 'B', documentId: 2, pageNumber: 8,
                sectionTitle: 'Section 4.0 Payment Terms',
                content: "Section 4.0 Payment Terms\nAll invoices shall be settled Net 30 days following acceptance.",
                startChar: 0, endChar: 80
            ),
            new DocumentChunkDTO(
                chunkId: 'doc_b_c2', documentSlot: 'B', documentId: 2, pageNumber: 20,
                sectionTitle: 'Section 14.0 Indemnification and Liability',
                content: "Section 14.0 Indemnification and Liability\nNeither party shall indemnify the other. Aggregate liability is capped at \$1,000,000 USD.",
                startChar: 0, endChar: 130
            ),
            // Note: Section 19 is omitted in Document B!
        ];

        // Store ephemerally in Redis/Cache
        $this->indexManager->storeChunks($this->job->id, 'A', $chunksA);
        $this->indexManager->storeChunks($this->job->id, 'B', $chunksB);

        // 3. Execute comparison orchestrator
        $orchestrator = app(ComparisonOrchestrator::class);
        $result = $orchestrator->compare($this->job);

        // 4. Verify results
        $this->assertNotEmpty($result->findings);
        $this->assertLessThanOrEqual(100, $result->similarityScore);

        // Verify database persistence of report & findings
        $this->assertDatabaseHas('comparison_reports', [
            'comparison_job_id' => $this->job->id,
        ]);

        $report = ComparisonReport::where('comparison_job_id', $this->job->id)->first();
        $this->assertNotNull($report);
        $this->assertGreaterThan(0, $report->high_risk_count);
        $this->assertGreaterThan(0, $report->findings()->count());

        // Check specific findings
        $findingTypes = $report->findings()->pluck('type')->map(fn($t) => $t->value)->all();
        $this->assertContains('financial', $findingTypes);
        $this->assertContains('modified', $findingTypes);
        $this->assertContains('missing', $findingTypes);
        $this->assertContains('contradiction', $findingTypes);

        // Verify evidence accuracy
        $finFinding = $report->findings()->where('type', 'financial')->first();
        $this->assertNotNull($finFinding);
        $this->assertEquals(18, $finFinding->doc_a_page);
        $this->assertEquals(20, $finFinding->doc_b_page);
    }

    public function test_document_aware_chat_endpoint_returns_citations(): void
    {
        // Store context chunks
        $chunksA = [
            new DocumentChunkDTO(
                chunkId: 'c_chat_a', documentSlot: 'A', documentId: 1, pageNumber: 7,
                sectionTitle: 'Payment Terms',
                content: "Invoices shall be paid Net 60 days.",
                startChar: 0, endChar: 35
            ),
        ];
        $chunksB = [
            new DocumentChunkDTO(
                chunkId: 'c_chat_b', documentSlot: 'B', documentId: 2, pageNumber: 8,
                sectionTitle: 'Payment Terms',
                content: "Invoices shall be paid Net 30 days.",
                startChar: 0, endChar: 35
            ),
        ];

        $this->indexManager->storeChunks($this->job->id, 'A', $chunksA);
        $this->indexManager->storeChunks($this->job->id, 'B', $chunksB);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/comparisons/{$this->job->id}/chat", [
                'message' => 'Compare the payment settlement terms between documents',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'user_message' => ['id', 'content', 'sender'],
                    'ai_response' => [
                        'id',
                        'content',
                        'sender',
                        'citations' => [
                            '*' => ['docLabel', 'page', 'quote'],
                        ],
                        'follow_ups',
                    ],
                ],
            ]);
    }
}
