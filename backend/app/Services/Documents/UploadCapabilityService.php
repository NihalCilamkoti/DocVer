<?php

namespace App\Services\Documents;

use App\Models\User;
use App\Models\Workspace;

class UploadCapabilityService
{
    public const DEFAULT_MAX_FILE_SIZE_MB = 50;
    public const DEFAULT_MAX_FILES_PER_UPLOAD = 10;

    public const SUPPORTED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'webp'
    ];

    public const SUPPORTED_FORMAT_CATEGORIES = [
        'PDF Documents' => ['.pdf'],
        'Microsoft Word' => ['.doc', '.docx'],
        'Text & Data' => ['.txt', '.csv'],
        'Spreadsheets' => ['.xls', '.xlsx'],
        'Presentations' => ['.ppt', '.pptx'],
        'Scanned Documents & Images' => ['.png', '.jpg', '.jpeg', '.webp'],
    ];

    public const PLAN_SPECIFICATIONS = [
        'starter' => [
            'name' => 'Starter / Free Plan',
            'max_file_size_mb' => 50,
            'max_storage_gb' => 1,
            'max_pages_per_doc' => 500,
            'max_files_per_upload' => 10,
        ],
        'professional' => [
            'name' => 'Professional Plan',
            'max_file_size_mb' => 100,
            'max_storage_gb' => 25,
            'max_pages_per_doc' => 2000,
            'max_files_per_upload' => 25,
        ],
        'enterprise' => [
            'name' => 'Enterprise Plan',
            'max_file_size_mb' => 250,
            'max_storage_gb' => 'Unlimited (Custom)',
            'max_pages_per_doc' => 'Unlimited',
            'max_files_per_upload' => 100,
        ],
    ];

    /**
     * Get real capability configuration for user/workspace.
     */
    public function getCapabilities(?User $user = null, ?Workspace $workspace = null): array
    {
        $planKey = strtolower($workspace?->plan ?? $user?->workspaces()->first()?->plan ?? 'professional');
        $planSpec = self::PLAN_SPECIFICATIONS[$planKey] ?? self::PLAN_SPECIFICATIONS['professional'];

        $maxFileSizeMb = $workspace?->max_file_size_mb ?? $planSpec['max_file_size_mb'];
        $maxPageLimit = $workspace?->max_page_limit ?? $planSpec['max_pages_per_doc'];

        return [
            'integrated_file_uploads_supported' => true,
            'current_plan' => $planSpec['name'],
            'plan_key' => $planKey,
            'max_file_size_mb' => $maxFileSizeMb,
            'max_file_size_bytes' => $maxFileSizeMb * 1024 * 1024,
            'max_file_size_human' => "{$maxFileSizeMb} MB per file",
            'max_storage_limit' => is_numeric($planSpec['max_storage_gb']) ? "{$planSpec['max_storage_gb']} GB total workspace storage" : (string) $planSpec['max_storage_gb'],
            'max_storage_gb' => $planSpec['max_storage_gb'],
            'max_pages_per_document' => $maxPageLimit,
            'max_files_per_upload' => $planSpec['max_files_per_upload'],
            'supported_extensions' => self::SUPPORTED_EXTENSIONS,
            'supported_categories' => self::SUPPORTED_FORMAT_CATEGORIES,
            'comparison_slots' => 2,
        ];
    }

    /**
     * Build an accurate, authoritative response text for file upload limits inquiry.
     */
    public function getUploadLimitsSummary(?User $user = null, ?Workspace $workspace = null): string
    {
        $caps = $this->getCapabilities($user, $workspace);
        $exts = implode(', ', array_map(fn($e) => strtoupper($e), self::SUPPORTED_EXTENSIONS));

        $storageGbText = is_numeric($caps['max_storage_gb']) ? "{$caps['max_storage_gb']} GB" : $caps['max_storage_gb'];

        return "In DocVer, you can upload up to **{$storageGbText}** of total workspace storage, with a maximum file size of **{$caps['max_file_size_mb']} MB per file** on your current **{$caps['current_plan']}**.\n\n" .
            "### DocVer Upload Specifications:\n" .
            "- **Maximum file size per file:** {$caps['max_file_size_mb']} MB (" . number_format($caps['max_file_size_bytes']) . " bytes)\n" .
            "- **Total workspace storage:** {$storageGbText}\n" .
            "- **Maximum files per upload batch:** {$caps['max_files_per_upload']} files\n" .
            "- **Maximum page limit per document:** {$caps['max_pages_per_document']} pages\n" .
            "- **Supported formats:** {$exts} (PDF, Microsoft Word, Text, Excel / Spreadsheets, PowerPoint, and Images)\n\n" .
            "### Plan Limits:\n" .
            "- **Starter / Free:** 50 MB per file, 1 GB total storage, 500 pages per document\n" .
            "- **Professional:** 100 MB per file, 25 GB total storage, 2,000 pages per document\n" .
            "- **Enterprise:** 250 MB per file, custom/unlimited total storage, unlimited pages\n\n" .
            "You can upload files directly using the **Attach File** button in the chat composer or by dragging and dropping documents into the workspace.";
    }
}
