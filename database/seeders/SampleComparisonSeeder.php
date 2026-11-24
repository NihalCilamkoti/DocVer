<?php

namespace Database\Seeders;

use App\Enums\ComparisonStatus;
use App\Enums\FindingType;
use App\Enums\RiskLevel;
use App\Models\ChatMessage;
use App\Models\ComparisonFinding;
use App\Models\ComparisonJob;
use App\Models\ComparisonReport;
use App\Models\DocumentMetadata;
use App\Models\UsageRecord;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SampleComparisonSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::where('slug', 'sterling-partners-llp')->first();
        $user = User::where('email', 'alex.sterling@enterprise-legal.com')->first();

        if (! $workspace || ! $user) {
            return;
        }

        // Create sample comparison job
        $job = ComparisonJob::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'Master Services Agreement v2.1 vs v2.4 (Updated)',
            'status' => ComparisonStatus::COMPLETED,
            'similarity_score' => 84,
            'started_at' => now()->subMinutes(15),
            'completed_at' => now()->subMinutes(14),
        ]);

        // Staged Document A
        DocumentMetadata::create([
            'workspace_id' => $workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'A',
            'original_name' => 'Master_Services_Agreement_v2.1_Executed.pdf',
            'storage_path' => "documents/{$workspace->id}/{$job->id}/doc_a.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 2450000,
            'page_count' => 42,
            'checksum_sha256' => hash('sha256', 'mock_doc_a_seed_data'),
            'is_sanitized' => true,
            'expires_at' => now()->addDays(30),
        ]);

        // Staged Document B
        DocumentMetadata::create([
            'workspace_id' => $workspace->id,
            'comparison_job_id' => $job->id,
            'slot' => 'B',
            'original_name' => 'Master_Services_Agreement_v2.4_Draft_Proposed.pdf',
            'storage_path' => "documents/{$workspace->id}/{$job->id}/doc_b.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 2780000,
            'page_count' => 45,
            'checksum_sha256' => hash('sha256', 'mock_doc_b_seed_data'),
            'is_sanitized' => true,
            'expires_at' => now()->addDays(30),
        ]);

        // Comparison Report
        $report = ComparisonReport::create([
            'comparison_job_id' => $job->id,
            'total_differences' => 14,
            'high_risk_count' => 3,
            'medium_risk_count' => 6,
            'low_risk_count' => 5,
            'matching_clauses_count' => 58,
            'missing_clauses_count' => 3,
            'modified_clauses_count' => 8,
            'numerical_discrepancies_count' => 4,
            'contradictions_count' => 2,
            'executive_summary' => 'Comprehensive redline audit complete. Found 3 critical liabilities: 50% liability reduction, Net 30 late interest penalty imposition, and work-for-hire assignment removal.',
            'generated_at' => now()->subMinutes(14),
        ]);

        // Findings
        ComparisonFinding::create([
            'comparison_report_id' => $report->id,
            'type' => FindingType::MODIFIED,
            'risk_level' => RiskLevel::HIGH,
            'category' => 'Indemnification & Liability',
            'title' => 'Aggregate Liability Cap Reduced by 50%',
            'doc_a_page' => 18,
            'doc_b_page' => 20,
            'explanation' => 'Liability limitation cap was reduced from 2x total contract value to 1x annual trailing fees, cutting recoverable damages in half.',
            'significance' => 'Directly shifts catastrophic breach exposure back to the client.',
            'metrics' => ['value_a' => '$2,000,000 USD', 'value_b' => '$1,000,000 USD', 'variance' => '-50%'],
            'order_index' => 1,
        ]);

        ComparisonFinding::create([
            'comparison_report_id' => $report->id,
            'type' => FindingType::MODIFIED,
            'risk_level' => RiskLevel::MEDIUM,
            'category' => 'Payment Terms',
            'title' => 'Invoice Settlement Window Shortened from 60 to 30 Days',
            'doc_a_page' => 7,
            'doc_b_page' => 8,
            'explanation' => 'Payment terms changed from Net 60 to Net 30 with new compound late penalty fee introduced.',
            'significance' => 'Accelerates cash outflow schedule and introduces monthly recurring interest penalties.',
            'metrics' => ['value_a' => 'Net 60 calendar days', 'value_b' => 'Net 30 business days + 1.5% interest'],
            'order_index' => 2,
        ]);

        ComparisonFinding::create([
            'comparison_report_id' => $report->id,
            'type' => FindingType::MISSING,
            'risk_level' => RiskLevel::HIGH,
            'category' => 'Intellectual Property',
            'title' => 'Work-For-Hire Assignment Clause Removed in Doc B',
            'doc_a_page' => 26,
            'doc_b_page' => 27,
            'explanation' => 'Document B removes explicit work-for-hire assignment, reverting deliverable IP to vendor with a non-exclusive license instead.',
            'significance' => 'Client risks losing exclusive intellectual property rights to custom-developed source assets.',
            'metrics' => null,
            'order_index' => 3,
        ]);

        // Seed chat conversation
        ChatMessage::create([
            'comparison_job_id' => $job->id,
            'user_id' => null,
            'sender' => 'ai',
            'content' => "I've analyzed Master_Services_Agreement_v2.1_Executed.pdf against Master_Services_Agreement_v2.4_Draft_Proposed.pdf. Found 14 total differences with 3 high-risk discrepancies around liability caps, payment terms, and intellectual property assignment. How would you like to investigate?",
            'citations' => [
                ['docLabel' => 'Document A', 'page' => 18, 'quote' => 'Total aggregate liability shall be limited to twice the annual contract value ($2,000,000 USD).'],
                ['docLabel' => 'Document B', 'page' => 20, 'quote' => 'Total aggregate liability shall not exceed fees paid in the immediately preceding twelve (12) months.'],
            ],
            'follow_ups' => [
                'Why was the liability cap flagged as high risk?',
                'What happened to the intellectual property clause?',
                'Summarize the SLA uptime difference.',
            ],
        ]);

        // Usage records
        UsageRecord::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'metric_type' => 'comparisons',
            'quantity' => 1,
            'recorded_at' => now(),
        ]);

        UsageRecord::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'metric_type' => 'pages_processed',
            'quantity' => 87,
            'recorded_at' => now(),
        ]);
    }
}
