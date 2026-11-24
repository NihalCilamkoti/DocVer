<?php

namespace Tests\Feature;

use App\Enums\ComparisonStatus;
use App\Enums\UserRole;
use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ComparisonApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Legal Auditor',
            'email' => 'auditor@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::WORKSPACE_ADMIN,
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Auditor Workspace',
            'slug' => 'auditor-workspace',
            'owner_id' => $this->user->id,
            'plan' => 'professional',
            'max_file_size_mb' => 50,
        ]);

        $this->workspace->members()->attach($this->user->id, ['role' => 'admin']);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_user_can_upload_documents(): void
    {
        $file = UploadedFile::fake()->createWithContent('Agreement.pdf', "%PDF-1.4\n%DocVer Sample\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF");

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/documents/upload", [
                'file' => $file,
                'slot' => 'A',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Agreement.pdf')
            ->assertJsonPath('data.slot', 'A');

        $this->assertDatabaseHas('document_metadata', [
            'original_name' => 'Agreement.pdf',
            'slot' => 'A',
        ]);
    }

    public function test_user_can_create_comparison_job(): void
    {
        $docA = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => null,
            'slot' => 'A',
            'original_name' => 'DocA.pdf',
            'storage_path' => 'doc_a.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'checksum_sha256' => hash('sha256', 'a'),
            'expires_at' => now()->addDays(30),
        ]);

        $docB = DocumentMetadata::create([
            'workspace_id' => $this->workspace->id,
            'comparison_job_id' => null,
            'slot' => 'B',
            'original_name' => 'DocB.pdf',
            'storage_path' => 'doc_b.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'checksum_sha256' => hash('sha256', 'b'),
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/v1/workspaces/{$this->workspace->id}/comparisons", [
                'title' => 'Master Agreement Redline Audit',
                'document_a_id' => $docA->id,
                'document_b_id' => $docB->id,
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Master Agreement Redline Audit');

        $this->assertDatabaseHas('comparison_jobs', [
            'title' => 'Master Agreement Redline Audit',
        ]);
    }

    public function test_ownership_isolation_blocks_unauthorized_user_from_accessing_other_workspace(): void
    {
        $otherUser = User::create([
            'name' => 'Intruder',
            'email' => 'intruder@other.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::USER,
        ]);

        $otherToken = $otherUser->createToken('intruder-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->getJson("/api/v1/workspaces/{$this->workspace->id}/comparisons");

        $response->assertStatus(403);
    }
}
