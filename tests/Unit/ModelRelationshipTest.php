<?php

namespace Tests\Unit;

use App\Enums\ComparisonStatus;
use App\Enums\FindingType;
use App\Enums\RiskLevel;
use App\Enums\UserRole;
use App\Models\ComparisonFinding;
use App\Models\ComparisonJob;
use App\Models\ComparisonReport;
use App\Models\DocumentMetadata;
use App\Models\UsageRecord;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_workspace_relationships_and_roles(): void
    {
        $user = User::create([
            'name' => 'Alice Legal',
            'email' => 'alice@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::WORKSPACE_OWNER,
        ]);

        $workspace = Workspace::create([
            'name' => 'Apex Legal LLC',
            'slug' => 'apex-legal-llc',
            'owner_id' => $user->id,
            'plan' => 'enterprise',
        ]);

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $this->assertTrue($user->isWorkspaceAdmin($workspace));
        $this->assertEquals(1, $user->ownedWorkspaces()->count());
        $this->assertEquals(1, $workspace->members()->count());
        $this->assertEquals('Apex Legal LLC', $user->workspaces->first()->name);
    }

    public function test_comparison_job_and_report_relationships(): void
    {
        $user = User::create([
            'name' => 'Bob Tester',
            'email' => 'bob@example.com',
            'password' => bcrypt('password123'),
            'role' => UserRole::USER,
        ]);

        $workspace = Workspace::create([
            'name' => 'Bob Workspace',
            'slug' => 'bob-workspace',
            'owner_id' => $user->id,
        ]);

        $job = ComparisonJob::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'Contract Diff Alpha vs Beta',
            'status' => ComparisonStatus::COMPLETED,
            'similarity_score' => 92,
        ]);

        $docA = DocumentMetadata::create([
            'workspace_id' => $workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'DocA.pdf',
            'storage_path' => 'dummy/a.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'checksum_sha256' => hash('sha256', 'a'),
            'expires_at' => now()->addDays(30),
        ]);

        $report = ComparisonReport::create([
            'comparison_job_id' => $job->id,
            'total_differences' => 2,
            'high_risk_count' => 1,
            'medium_risk_count' => 1,
            'low_risk_count' => 0,
            'executive_summary' => 'Minor changes detected.',
        ]);

        $finding = ComparisonFinding::create([
            'comparison_report_id' => $report->id,
            'type' => FindingType::MODIFIED,
            'risk_level' => RiskLevel::HIGH,
            'category' => 'Liability',
            'title' => 'Indemnity Cap Modified',
            'explanation' => 'Cap reduced.',
        ]);

        $this->assertEquals($job->id, $report->comparisonJob->id);
        $this->assertEquals(1, $report->findings()->count());
        $this->assertEquals($finding->title, $job->report->findings->first()->title);
        $this->assertEquals('DocA.pdf', $job->documentA->original_name);
    }
}
