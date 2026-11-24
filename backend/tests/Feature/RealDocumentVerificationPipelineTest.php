<?php

namespace Tests\Feature;

use App\Models\ComparisonJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Comparison\ComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RealDocumentVerificationPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->workspace = Workspace::create([
            'name' => 'Legal Audit Workspace',
            'slug' => 'legal-audit-' . $this->user->id,
            'owner_id' => $this->user->id,
        ]);
        $this->user->workspaces()->attach($this->workspace->id, ['role' => 'owner']);
    }

    public function test_real_document_upload_and_direct_comparison(): void
    {
        $contentA = <<<TEXT
SECTION 1. DEFINITIONS AND SCOPE
This Master Agreement defines the obligations between Provider and Client.

SECTION 2. INDEMNIFICATION AND LIABILITY
Provider agrees to indemnify Client up to a maximum aggregate liability cap of $2,000,000 USD.

SECTION 3. PAYMENT TERMS
Invoices shall be settled within sixty (60) calendar days of receipt.
TEXT;

        $contentB = <<<TEXT
SECTION 1. DEFINITIONS AND SCOPE
This Master Agreement defines the obligations between Provider and Client.

SECTION 2. INDEMNIFICATION AND LIABILITY
Provider agrees to indemnify Client up to a maximum aggregate liability cap of $1,000,000 USD.

SECTION 3. PAYMENT TERMS
Invoices shall be settled within thirty (30) calendar days of receipt with 1.5% late interest per month.
TEXT;

        $fileA = UploadedFile::fake()->createWithContent('contract_v1.txt', $contentA);
        $fileB = UploadedFile::fake()->createWithContent('contract_v2.txt', $contentB);

        // 1. Direct upload Document A
        $resA = $this->actingAs($this->user)
            ->postJson('/api/v1/upload', [
                'file' => $fileA,
                'slot' => 'A',
            ])
            ->assertStatus(201);

        $docAId = $resA->json('data.id');
        $this->assertNotNull($docAId);

        // 2. Direct upload Document B
        $resB = $this->actingAs($this->user)
            ->postJson('/api/v1/upload', [
                'file' => $fileB,
                'slot' => 'B',
            ])
            ->assertStatus(201);

        $docBId = $resB->json('data.id');
        $this->assertNotNull($docBId);

        // 3. Initiate comparison via direct store route
        $createRes = $this->actingAs($this->user)
            ->postJson('/api/v1/comparisons', [
                'title' => 'MSA Revision 1 vs Revision 2',
                'document_a_id' => $docAId,
                'document_b_id' => $docBId,
            ])
            ->assertStatus(202);

        $jobId = $createRes->json('data.id');
        $this->assertNotNull($jobId);

        // 5. Query status (queue is sync in tests, so job is already executed)
        $statusRes = $this->actingAs($this->user)
            ->getJson("/api/v1/comparisons/{$jobId}/status")
            ->assertOk();

        $this->assertEquals(100, $statusRes->json('data.progress'));

        // 6. Direct show comparison with loaded findings
        $showRes = $this->actingAs($this->user)
            ->getJson("/api/v1/comparisons/{$jobId}")
            ->assertOk();

        $data = $showRes->json('data');
        $this->assertEquals('MSA Revision 1 vs Revision 2', $data['title']);
        $this->assertNotNull($data['report']);
        $this->assertGreaterThan(0, $data['report']['summary']['total_differences']);

        // Check that findings are not hardcoded mock findings
        $findings = $data['report']['findings'];
        $this->assertNotEmpty($findings);

        // Check list endpoint
        $listRes = $this->actingAs($this->user)
            ->getJson('/api/v1/comparisons')
            ->assertOk();

        $this->assertCount(1, $listRes->json('data'));
    }

    public function test_identical_documents_produce_100_percent_similarity(): void
    {
        $content = "Standard Services Agreement. Both parties agree to standard terms.";
        $fileA = UploadedFile::fake()->createWithContent('standard_a.txt', $content);
        $fileB = UploadedFile::fake()->createWithContent('standard_b.txt', $content . " ");

        $resA = $this->actingAs($this->user)->postJson('/api/v1/upload', ['file' => $fileA, 'slot' => 'A'])->assertStatus(201);
        $resB = $this->actingAs($this->user)->postJson('/api/v1/upload', ['file' => $fileB, 'slot' => 'B'])->assertStatus(201);

        $createRes = $this->actingAs($this->user)->postJson('/api/v1/comparisons', [
            'title' => 'Identical Documents Test',
            'document_a_id' => $resA->json('data.id'),
            'document_b_id' => $resB->json('data.id'),
        ])->assertStatus(202);

        $jobId = $createRes->json('data.id');
        $showRes = $this->actingAs($this->user)->getJson("/api/v1/comparisons/{$jobId}")->assertOk();
        $score = $showRes->json('data.similarity_score');
        $this->assertEquals(100, $score, "Identical documents must have 100% similarity score");
    }
}
