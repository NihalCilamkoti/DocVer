<?php

namespace Tests\Feature;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIOrchestrator;
use App\Services\Comparison\ComparisonOrchestrator;
use App\Services\Comparison\DocumentRelationshipClassifier;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentComparisonModesAndUserFriendlyReportingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create([
            'name' => 'Comparison Modes Test Workspace',
            'slug' => 'modes-ws',
            'owner_id' => $this->user->id,
            'plan' => 'professional',
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);
    }

    public function test_document_relationship_classifier_identifies_different_purposes_for_schedule_and_roles(): void
    {
        $classifier = app(DocumentRelationshipClassifier::class);

        $structureAnalyzer = app(\App\Services\Comparison\DocumentStructureAnalyzer::class);
        $chunksA = [
            [
                'id' => 1,
                'page_number' => 1,
                'section_title' => 'Schedule',
                'content' => "Schedule\nWake up at 5:00 am EVERYDAY\n5:00 am - 5:30 am = yoga or meditation\n6:00 am - 7:00 am = gym\n9:10 am - 4:30 pm = College life",
            ],
        ];
        $chunksB = [
            [
                'id' => 2,
                'page_number' => 1,
                'section_title' => 'ROLES',
                'content' => "ROLES\nRoles for each member for next 2-3months\n1. Product and vision lead TAKEN BY NIHAL AND GIDEON RAJ\n2. Technical lead TAKEN BY GIDEON RAJ AND NIHAL",
            ],
        ];

        $secA = $structureAnalyzer->analyzeStructure($chunksA, 'A');
        $secB = $structureAnalyzer->analyzeStructure($chunksB, 'B');

        $rel = $classifier->classify($secA, $secB, 'Schedule.pdf', 'roles assigned.pdf');

        $this->assertEquals('different_purposes', $rel->relationship);
        $this->assertFalse($rel->isVersionRevision());
        $this->assertEquals('general_comparison', $rel->recommendedMode);
        $this->assertStringContainsString('different purposes', strtolower($rel->relationshipSummary));
        $this->assertStringContainsString('not revisions', strtolower($rel->relationshipSummary));
    }

    public function test_comparison_orchestrator_does_not_generate_fake_removed_clauses_for_unrelated_documents(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Schedule vs Roles General Comparison',
            'status' => 'queued',
        ]);

        $docA = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'Schedule.pdf',
            'storage_path' => 'documents/test/schedule.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 40000,
            'checksum_sha256' => hash('sha256', 'schedule'),
            'expires_at' => now()->addDays(30),
        ]);

        $docB = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'roles assigned.pdf',
            'storage_path' => 'documents/test/roles.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 110000,
            'checksum_sha256' => hash('sha256', 'roles'),
            'expires_at' => now()->addDays(30),
        ]);

        $indexManager = app(EphemeralIndexManager::class);
        $indexManager->storeChunks($job->id, 'A', [
            [
                'id' => 'c_a_1',
                'chunk_id' => 'c_a_1',
                'page_number' => 1,
                'section_title' => 'Schedule',
                'content' => "Schedule\nWake up at 5:00 am EVERYDAY\n5:00 am - 5:30 am = yoga or meditation\n6:00 am - 7:00 am = gym",
                'token_count' => 50,
            ],
        ]);
        $indexManager->storeChunks($job->id, 'B', [
            [
                'id' => 'c_b_1',
                'chunk_id' => 'c_b_1',
                'page_number' => 1,
                'section_title' => 'ROLES',
                'content' => "ROLES\nRoles for each member for next 2-3months\n1. Product and vision lead TAKEN BY NIHAL AND GIDEON RAJ\n2. Technical lead TAKEN BY GIDEON RAJ AND NIHAL",
                'token_count' => 60,
            ],
        ]);

        $orchestrator = app(ComparisonOrchestrator::class);
        $result = $orchestrator->compare($job);

        // 1. Must NOT report high risk discrepancies
        $this->assertEquals(0, $result->summary['high_risk_count']);

        // 2. Must NOT claim clauses were removed
        $this->assertEquals(0, $result->summary['missing_clauses_count']);

        // 3. Findings must NOT contain "Clause Removed" or "Operational and legal review recommended"
        foreach ($result->findings as $f) {
            $this->assertStringNotContainsString('Clause Removed', $f->title);
            $this->assertStringNotContainsString('New Clause Added', $f->title);
            $this->assertStringNotContainsString('Operational and legal review recommended', $f->significance ?? '');
        }

        // 4. Executive summary must state different purposes
        $this->assertStringContainsString('different purposes', strtolower($result->executiveSummary));
        $this->assertStringContainsString('not revisions', strtolower($result->executiveSummary));
    }

    public function test_chat_attribution_identifies_correct_document_for_schedule_and_roles(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Schedule vs Roles Attribution Test',
            'status' => 'completed',
        ]);

        $docA = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'Schedule.pdf',
            'storage_path' => 'documents/test/schedule.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 40000,
            'checksum_sha256' => hash('sha256', 'schedule'),
            'expires_at' => now()->addDays(30),
        ]);

        $docB = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'roles assigned.pdf',
            'storage_path' => 'documents/test/roles.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 110000,
            'checksum_sha256' => hash('sha256', 'roles'),
            'expires_at' => now()->addDays(30),
        ]);

        $indexManager = app(EphemeralIndexManager::class);
        $indexManager->storeChunks($job->id, 'A', [
            [
                'id' => 'c_a_1',
                'chunk_id' => 'c_a_1',
                'page_number' => 1,
                'section_title' => 'Schedule',
                'content' => "Schedule\nWake up at 5:00 am EVERYDAY\n5:00 am - 5:30 am = yoga or meditation\n6:00 am - 7:00 am = gym",
                'token_count' => 50,
            ],
        ]);
        $indexManager->storeChunks($job->id, 'B', [
            [
                'id' => 'c_b_1',
                'chunk_id' => 'c_b_1',
                'page_number' => 1,
                'section_title' => 'ROLES',
                'content' => "ROLES\nRoles for each member for next 2-3months\n1. Product and vision lead TAKEN BY NIHAL AND GIDEON RAJ\n2. Technical lead TAKEN BY GIDEON RAJ AND NIHAL",
                'token_count' => 60,
            ],
        ]);

        $classifier = app(\App\Services\AI\Intent\TaskIntentClassifier::class);

        // Test attribution query 1
        $intent1 = $classifier->classify('Which document contains the daily routine?');
        $this->assertEquals('document_attribution', $intent1->intent);

        // Test attribution query 2
        $intent2 = $classifier->classify('Which document explains team responsibilities?');
        $this->assertEquals('document_attribution', $intent2->intent);

        // Test version relationship query 3
        $intent3 = $classifier->classify('Are these two versions of the same document?');
        $this->assertEquals('version_relationship_check', $intent3->intent);
    }

    public function test_version_comparison_still_works_for_actual_contract_revisions(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Contract v1 vs v2',
            'status' => 'queued',
        ]);

        DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'Master_Services_Agreement_v1.pdf',
            'storage_path' => 'documents/test/msa_v1.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 50000,
            'checksum_sha256' => hash('sha256', 'msa_v1'),
            'expires_at' => now()->addDays(30),
        ]);

        DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'Master_Services_Agreement_v2_revised.pdf',
            'storage_path' => 'documents/test/msa_v2.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 52000,
            'checksum_sha256' => hash('sha256', 'msa_v2'),
            'expires_at' => now()->addDays(30),
        ]);

        $indexManager = app(EphemeralIndexManager::class);
        $indexManager->storeChunks($job->id, 'A', [
            [
                'id' => 'msa_a_1',
                'chunk_id' => 'msa_a_1',
                'page_number' => 1,
                'section_title' => 'Article 1. Payment Terms',
                'content' => 'Article 1. Payment Terms: Invoices shall be payable within 60 days of receipt.',
                'token_count' => 20,
            ],
            [
                'id' => 'msa_a_2',
                'chunk_id' => 'msa_a_2',
                'page_number' => 2,
                'section_title' => 'Article 2. Indemnification',
                'content' => 'Article 2. Indemnification: Vendor agrees to hold Customer harmless against IP infringement claims.',
                'token_count' => 25,
            ],
        ]);
        $indexManager->storeChunks($job->id, 'B', [
            [
                'id' => 'msa_b_1',
                'chunk_id' => 'msa_b_1',
                'page_number' => 1,
                'section_title' => 'Article 1. Payment Terms',
                'content' => 'Article 1. Payment Terms: Invoices shall be payable within 30 days of receipt.',
                'token_count' => 20,
            ],
            // Article 2 Indemnification is removed in Doc B
        ]);

        $orchestrator = app(ComparisonOrchestrator::class);
        $result = $orchestrator->compare($job);

        // Genuine version comparison: detects the modification and removal
        $this->assertGreaterThan(0, $result->summary['total_differences']);
    }
}
