<?php

namespace Tests\Feature;

use App\Enums\FindingType;
use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AIOrchestrator;
use App\Services\AI\Intent\TaskIntentClassifier;
use App\Services\Comparison\ComparisonOrchestrator;
use App\Services\Comparison\DocumentRelationshipClassifier;
use App\Services\Comparison\DTOs\AnalyzedSectionDTO;
use App\Services\Comparison\RoleExtractionService;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntelligentDocumentComparisonAndRoleExtractionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected EphemeralIndexManager $indexManager;
    protected RoleExtractionService $roleService;
    protected AIOrchestrator $orchestrator;
    protected TaskIntentClassifier $intentClassifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create([
            'name' => 'Intelligent Comparison Workspace',
            'slug' => 'intel-ws-' . uniqid(),
            'owner_id' => $this->user->id,
            'plan' => 'enterprise',
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);

        $this->indexManager = app(EphemeralIndexManager::class);
        $this->roleService = app(RoleExtractionService::class);
        $this->orchestrator = app(AIOrchestrator::class);
        $this->intentClassifier = app(TaskIntentClassifier::class);
    }

    /**
     * TEST A: Different Document Purposes
     * Compare a personal schedule with a team roles document.
     * Verify findings do NOT classify all unique sections as "Modified" or "Removed" clauses.
     */
    public function test_a_different_document_purposes_avoids_false_modifications_and_removed_clauses(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Schedule vs Roles Assigned',
            'status' => 'queued',
        ]);

        $docA = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'Schedule.pdf',
            'storage_path' => 'documents/test/schedule.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 45000,
            'checksum_sha256' => hash('sha256', 'schedule_data'),
            'expires_at' => now()->addDays(30),
        ]);

        $docB = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'roles assigned.pdf',
            'storage_path' => 'documents/test/roles_assigned.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 120000,
            'checksum_sha256' => hash('sha256', 'roles_data'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->indexManager->storeChunks($job->id, 'A', [
            [
                'id' => 'chunk_sched_1',
                'chunk_id' => 'chunk_sched_1',
                'page_number' => 1,
                'section_title' => 'Daily Schedule',
                'content' => "Daily Schedule\n5:00 am - 5:30 am = Meditation & morning routine\n6:00 am - 7:00 am = Gym and fitness workout\n9:10 am - 4:30 pm = University lectures & coursework",
                'token_count' => 50,
            ],
        ]);

        $this->indexManager->storeChunks($job->id, 'B', [
            [
                'id' => 'chunk_roles_1',
                'chunk_id' => 'chunk_roles_1',
                'page_number' => 1,
                'section_title' => 'Team Roles Assigned',
                'content' => "ROLES ASSIGNED:\n- Gideon Raj and Nihal: Technology behind the camera, web application, and blockchain.\n- Sarah Connor: Product vision and design\nTotal core team size: 3 members",
                'token_count' => 60,
            ],
        ]);

        $compOrchestrator = app(ComparisonOrchestrator::class);
        $result = $compOrchestrator->compare($job);

        // Discrepancy classifications must be accurate:
        // Must NOT classify sections as 'modified' or 'missing' (removed)
        $this->assertEquals(0, $result->summary['high_risk_count']);
        $this->assertEquals(0, $result->summary['missing_clauses_count']);
        $this->assertEquals(0, $result->summary['modified_clauses_count']);

        // Check finding types: must be unique_a, unique_b, or not_comparable
        $findingTypes = array_map(fn($f) => $f->type, $result->findings);
        $this->assertContains('not_comparable', $findingTypes);
        $this->assertContains('unique_a', $findingTypes);
        $this->assertContains('unique_b', $findingTypes);
        $this->assertNotContains('modified', $findingTypes);
        $this->assertNotContains('missing', $findingTypes);

        // Executive summary must describe distinct purposes
        $this->assertStringContainsString('different purposes', strtolower($result->executiveSummary));
        $this->assertStringContainsString('not revisions', strtolower($result->executiveSummary));
    }

    /**
     * TEST B: Technical Role Extraction
     * Ask who handles technical responsibilities in Document B.
     * Extract all assigned people and their actual responsibilities.
     */
    public function test_b_technical_role_extraction_directly_identifies_assigned_persons(): void
    {
        $rawText = "ROLES ASSIGNED:\n- Gideon Raj and Nihal: Technology behind the camera, web application, and blockchain.\n- Sarah Connor: Product vision and design\nTotal core team size: 3 members";
        $extracted = $this->roleService->extractFromText($rawText, 'B', 'roles assigned.pdf', 1);

        $answer = $this->roleService->answerRoleQuery(
            query: 'Who is taking the technical role in Document 2?',
            extractions: $extracted,
            docName: 'roles assigned.pdf'
        );

        $this->assertNotNull($answer);
        $directText = $answer['direct_answer'];

        // Directly identifies assigned persons
        $this->assertStringContainsString('Gideon Raj', $directText);
        $this->assertStringContainsString('Nihal', $directText);

        // Identifies actual responsibilities
        $this->assertStringContainsString('camera', strtolower($directText));
        $this->assertStringContainsString('web application', strtolower($directText));
        $this->assertStringContainsString('blockchain', strtolower($directText));

        // Source citation
        $this->assertNotNull($answer['citation']);
        $this->assertEquals('Document B', $answer['citation']['docLabel']);
        $this->assertEquals(1, $answer['citation']['page']);
        $this->assertStringContainsString('Technology behind the camera', $answer['citation']['quote']);
    }

    /**
     * TEST C: Joint Assignments
     * Verify that responsibilities assigned to two people are reported as joint assignments.
     */
    public function test_c_joint_assignments_are_reported_jointly_without_dropping_assignees(): void
    {
        $rawText = "- Gideon Raj and Nihal: Technology behind the camera, web application, and blockchain.";
        $extracted = $this->roleService->extractFromText($rawText, 'B', 'roles assigned.pdf', 1);

        $this->assertCount(1, $extracted['assignments']);
        $assign = $extracted['assignments'][0];

        $this->assertTrue($assign['is_joint_assignment']);
        $this->assertEquals(['Gideon Raj', 'Nihal'], $assign['assigned_persons']);

        $answer = $this->roleService->answerRoleQuery(
            query: 'Who handles the technology?',
            extractions: $extracted,
            docName: 'roles assigned.pdf'
        );

        $this->assertStringContainsString('assigned jointly', $answer['direct_answer']);
        $this->assertStringContainsString('Gideon Raj and Nihal', $answer['direct_answer']);
    }

    /**
     * TEST D: Formal Titles vs Descriptive Responsibilities
     * If no CTO title exists, do not invent one.
     */
    public function test_d_formal_titles_are_not_invented_when_only_responsibilities_described(): void
    {
        $rawText = "- Gideon Raj and Nihal: Technology behind the camera, web application, and blockchain.";
        $extracted = $this->roleService->extractFromText($rawText, 'B', 'roles assigned.pdf', 1);

        $assign = $extracted['assignments'][0];
        $this->assertFalse($assign['has_formal_title']);
        $this->assertNull($assign['role_title']);

        $answer = $this->roleService->answerRoleQuery(
            query: 'Who is taking the technical role in Document 2?',
            extractions: $extracted,
            docName: 'roles assigned.pdf'
        );

        $directText = $answer['direct_answer'];
        // Does not label as CTO
        $this->assertStringNotContainsString('is the CTO', $directText);
        $this->assertStringNotContainsString('serves as CTO', $directText);
        $this->assertStringNotContainsString('role of CTO', $directText);
        // Explicitly clarifies that no formal title was established
        $this->assertStringContainsString('no formal', strtolower($directText));
    }

    /**
     * TEST E: Simple Comparison
     * Ask for differences and return a clear summary, not an unnecessary legal audit.
     */
    public function test_e_simple_comparison_returns_clean_summary_not_unnecessary_legal_audit(): void
    {
        $job = ComparisonJob::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'title' => 'Schedule vs Roles',
            'status' => 'completed',
        ]);

        DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'Schedule.pdf',
            'storage_path' => 'documents/test/sched.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 45000,
            'checksum_sha256' => hash('sha256', 'sched'),
            'expires_at' => now()->addDays(30),
        ]);

        DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'roles assigned.pdf',
            'storage_path' => 'documents/test/roles.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 120000,
            'checksum_sha256' => hash('sha256', 'roles'),
            'expires_at' => now()->addDays(30),
        ]);

        $this->indexManager->storeChunks($job->id, 'A', [
            [
                'id' => 's1',
                'chunk_id' => 's1',
                'page_number' => 1,
                'section_title' => 'Schedule',
                'content' => "Schedule\nWake up at 5:00 am EVERYDAY\n6:00 am = gym",
                'token_count' => 20,
            ],
        ]);

        $this->indexManager->storeChunks($job->id, 'B', [
            [
                'id' => 'r1',
                'chunk_id' => 'r1',
                'page_number' => 1,
                'section_title' => 'Roles Assigned',
                'content' => "ROLES ASSIGNED:\n- Gideon Raj and Nihal: Technology behind the camera, web application, and blockchain.",
                'token_count' => 25,
            ],
        ]);

        $response = $this->orchestrator->answerComparisonQuery(
            job: $job,
            query: 'Give me the difference between these documents.'
        );

        $content = $response['content'];

        // Must follow user-friendly 6-part comparison format
        $this->assertStringContainsString('Documents Compared', $content);
        $this->assertStringContainsString('Overall Difference', $content);
        $this->assertStringContainsString('Main Differences', $content);
        $this->assertStringContainsString('Unique to', $content);
        $this->assertStringContainsString('Important Conclusion', $content);

        // Must NOT contain unrequested enterprise legal boilerplate or penalty scores
        $this->assertStringNotContainsString('High material impact on contract enforceability', $content);
        $this->assertStringNotContainsString('Identified legal contradictions in obligations', $content);
        $this->assertStringNotContainsString('Audit finding severity: HIGH', $content);
    }

    /**
     * TEST F: Specific Comparison
     * Ask only for numerical differences and exclude unrelated information.
     */
    public function test_f_specific_comparison_filters_to_requested_domain_only(): void
    {
        $intent = $this->intentClassifier->classify('Show only the changes in payment terms.');
        $this->assertContains('payment', $intent->targetClauses);
        $this->assertTrue($intent->isClauseDiff());

        $numIntent = $this->intentClassifier->classify('Give me only numerical differences.');
        $this->assertTrue($numIntent->isOnlyNumerical());
    }

    /**
     * TEST G: Unique Information Classification
     * Distinguish unique sections from genuinely removed or added revisions.
     */
    public function test_g_unique_sections_are_distinguished_from_added_or_removed_revisions(): void
    {
        $sectionsA = [
            new AnalyzedSectionDTO('sec_1', 'Morning Routine', 'Daily routine from 5am to 9am', 'Schedule', 1, 1, 20),
        ];
        $sectionsB = [
            new AnalyzedSectionDTO('sec_2', 'Technology Assignments', 'Gideon Raj and Nihal take technology', 'Roles', 1, 1, 25),
        ];

        $classifier = app(DocumentRelationshipClassifier::class);
        $rel = $classifier->classify($sectionsA, $sectionsB, 'Schedule.pdf', 'roles assigned.pdf');

        $this->assertEquals('different_purposes', $rel->relationship);
        $this->assertNotEmpty($rel->uniqueA);
        $this->assertNotEmpty($rel->uniqueB);

        // Check orchestrator candidate findings creation
        $compOrchestrator = app(ComparisonOrchestrator::class);
        $reflection = new \ReflectionClass($compOrchestrator);
        $method = $reflection->getMethod('buildGeneralComparisonFindings');
        $method->setAccessible(true);

        $findings = $method->invoke($compOrchestrator, $rel, $sectionsA, $sectionsB, 'Schedule.pdf', 'roles assigned.pdf');

        $types = array_map(fn($f) => $f->type, $findings);
        $this->assertContains('not_comparable', $types);
        $this->assertContains('unique_a', $types);
        $this->assertContains('unique_b', $types);
        $this->assertNotContains('modified', $types);
        $this->assertNotContains('missing', $types);
    }

    /**
     * TEST H: Similarity Score Calculation & Explanation
     * Verify that displayed score is calculated using a documented method and explained clearly.
     */
    public function test_h_similarity_score_is_multidimensional_and_explained(): void
    {
        $sectionsA = [
            new AnalyzedSectionDTO('sec_1', 'Daily Routine', 'Meditation, gym, breakfast, study', 'Schedule', 1, 1, 20),
        ];
        $sectionsB = [
            new AnalyzedSectionDTO('sec_2', 'Core Team Roles', 'Product vision, technology, marketing', 'Roles', 1, 1, 25),
        ];

        $classifier = app(DocumentRelationshipClassifier::class);
        $rel = $classifier->classify($sectionsA, $sectionsB, 'Schedule.pdf', 'roles assigned.pdf');

        $this->assertNotNull($rel->textualOverlap);
        $this->assertNotNull($rel->structuralSimilarity);
        $this->assertNotNull($rel->topicSimilarity);
        $this->assertNotEmpty($rel->similarityExplanation);

        // Low overlap is explicitly explained as different operational purposes, not broken revisions
        $this->assertStringContainsString('different operational purposes', $rel->similarityExplanation);
    }

    /**
     * TEST I: Citation Validation
     * Verify every cited source and page corresponds to genuine document text.
     */
    public function test_i_citation_validation_verifies_source_document_and_page(): void
    {
        $rawText = "DOCVER ENTERPRISE - TEAM ROLES\n- Nihal Cilamkoti: Product Lead and Principal AI Architect.\n- Priya Sharma: Full-Stack Engineering Lead.";
        $extracted = $this->roleService->extractFromText($rawText, 'B', 'roles assigned.pdf', 2);

        $answer = $this->roleService->answerRoleQuery(
            query: 'What is Nihal\'s role?',
            extractions: $extracted,
            docName: 'roles assigned.pdf'
        );

        $this->assertNotNull($answer['citation']);
        $this->assertEquals(2, $answer['citation']['page']);
        $this->assertStringContainsString('Nihal Cilamkoti', $answer['citation']['quote']);
    }

    /**
     * TEST J: Instruction Following and Member Count
     * Ensure output matches requested level of detail (e.g. member count, individual roles).
     */
    public function test_j_instruction_following_for_member_count_and_individual_roles(): void
    {
        $rawText = "TEAM DIRECTORY:\n- Nihal Cilamkoti: Product Lead\n- Priya Sharma: Full-Stack Engineering Lead\n- Alex Mercer: DevOps Engineer\nTotal Core Team Size: Three (3)";
        $extracted = $this->roleService->extractFromText($rawText, 'B', 'roles assigned.pdf', 1);

        $this->assertEquals(3, $extracted['explicit_member_count']);

        $answer = $this->roleService->answerRoleQuery(
            query: 'Give me individual roles and how many members are there.',
            extractions: $extracted,
            docName: 'roles assigned.pdf'
        );

        $directText = $answer['direct_answer'];

        // Must state member count first
        $this->assertStringStartsWith('There are **3 members**', $directText);

        // Must list individual members and their roles
        $this->assertStringContainsString('Nihal Cilamkoti', $directText);
        $this->assertStringContainsString('Priya Sharma', $directText);
        $this->assertStringContainsString('Alex Mercer', $directText);
    }
}
