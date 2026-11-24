<?php

namespace Tests\Feature;

use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadIntegrationAndDocumentQABugFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();

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
     * BUG 1 TEST: AI must accurately report real backend upload capabilities & limits.
     * Must NEVER claim "I cannot accept file uploads directly".
     */
    public function test_bug1_upload_limits_returns_accurate_backend_specifications(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'How much GB of files I can upload?',
        ]);

        $response->assertStatus(200);
        $content = $response->json('data.ai_response.content');

        // Assert accurate configuration from backend
        $this->assertStringContainsString('25 GB', $content);
        $this->assertStringContainsString('100 MB', $content);
        $this->assertStringContainsString('PDF', $content);
        $this->assertStringContainsString('Professional Plan', $content);

        // Assert complete absence of text-only disclaimers
        $this->assertStringNotContainsStringIgnoringCase('cannot accept file uploads', $content);
        $this->assertStringNotContainsStringIgnoringCase('only process text that you paste', $content);
    }

    /**
     * BUG 2 TEST: Attached document content is reliably extracted and answered with citations.
     */
    public function test_bug2_attached_document_roles_query_returns_actual_members_and_roles(): void
    {
        $samplePdfPath = '/Users/nihalcilamkoti/Documents/Final IDEA/roles assigned.pdf';
        $file = file_exists($samplePdfPath)
            ? new UploadedFile($samplePdfPath, 'roles assigned.pdf', 'application/pdf', null, true)
            : UploadedFile::fake()->create('roles assigned.pdf', 120, 'application/pdf');

        $doc = app(\App\Services\Documents\DocumentService::class)->stageDocument(
            workspace: $this->workspace,
            comparisonJobId: null,
            slot: 'A',
            file: $file
        );

        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => 'Give me individual roles and how many members are there?',
            'document_ids' => [$doc->id],
        ]);

        $response->assertStatus(200);
        $aiResp = $response->json('data.ai_response');
        $content = $aiResp['content'];

        $this->assertNotEmpty($content);
        // Must NOT claim no document was provided
        $this->assertStringNotContainsStringIgnoringCase('no document was provided', $content);
        $this->assertStringNotContainsStringIgnoringCase('cannot accept file uploads', $content);
        $this->assertStringNotContainsStringIgnoringCase('please paste the document', $content);

        // When testing with the actual roles assigned.pdf, verify roles and names
        if (file_exists($samplePdfPath)) {
            $this->assertStringContainsStringIgnoringCase('Nihal', $content);
            $this->assertStringContainsStringIgnoringCase('Product', $content);
            $this->assertStringContainsStringIgnoringCase('Technical', $content);
            $this->assertNotEmpty($aiResp['citations']);
        }
    }

    /**
     * BUG 2 (Follow-up) TEST: Typo in 'docuement B' correctly resolves slot B document.
     */
    public function test_slot_b_typo_resolves_correct_document(): void
    {
        $sampleRolesPath = '/Users/nihalcilamkoti/Documents/Final IDEA/roles assigned.pdf';
        $rolesFile = file_exists($sampleRolesPath)
            ? new UploadedFile($sampleRolesPath, 'roles assigned.pdf', 'application/pdf', null, true)
            : UploadedFile::fake()->create('roles assigned.pdf', 120, 'application/pdf');

        $sampleSchedPath = '/Users/nihalcilamkoti/Documents/Final IDEA/Schedule.pdf';
        $schedFile = file_exists($sampleSchedPath)
            ? new UploadedFile($sampleSchedPath, 'Schedule.pdf', 'application/pdf', null, true)
            : UploadedFile::fake()->create('Schedule.pdf', 50, 'application/pdf');

        $docA = app(\App\Services\Documents\DocumentService::class)->stageDocument(
            workspace: $this->workspace,
            comparisonJobId: null,
            slot: 'A',
            file: $schedFile
        );

        $docB = app(\App\Services\Documents\DocumentService::class)->stageDocument(
            workspace: $this->workspace,
            comparisonJobId: null,
            slot: 'B',
            file: $rolesFile
        );

        $response = $this->actingAs($this->user)->postJson('/api/v1/chat', [
            'message' => "what is Nihal's role in docuement B",
            'document_ids' => [$docA->id, $docB->id],
        ]);

        $response->assertStatus(200);
        $content = $response->json('data.ai_response.content');

        if (file_exists($sampleRolesPath)) {
            $this->assertStringContainsStringIgnoringCase('Nihal', $content);
            $this->assertStringContainsStringIgnoringCase('roles assigned.pdf', $content);
        }
    }
}
