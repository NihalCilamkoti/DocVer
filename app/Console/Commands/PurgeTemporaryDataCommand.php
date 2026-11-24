<?php

namespace App\Console\Commands;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Services\Documents\DocumentService;
use App\Services\Documents\Indexing\EphemeralIndexManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeTemporaryDataCommand extends Command
{
    protected $signature = 'docver:purge-temporary';
    protected $description = 'Purge expired staging files and abandoned ephemeral comparison indexes';

    public function handle(DocumentService $documentService, EphemeralIndexManager $indexManager): int
    {
        $this->info('Starting DocVer temporary data privacy purge...');

        // 1. Purge expired document metadata & disk binaries
        $purgedCount = $documentService->purgeExpiredDocuments();
        $this->info("Purged {$purgedCount} expired document records from storage.");

        // 2. Purge orphaned staging files older than 2 hours
        $allFiles = Storage::disk('local')->allFiles('documents');
        $deletedFiles = 0;
        $twoHoursAgo = time() - 7200;

        foreach ($allFiles as $file) {
            $lastModified = Storage::disk('local')->lastModified($file);
            if ($lastModified < $twoHoursAgo) {
                Storage::disk('local')->delete($file);
                $deletedFiles++;
            }
        }
        $this->info("Purged {$deletedFiles} orphaned temporary file binaries.");

        // 3. Clear ephemeral Redis indexes for completed/cancelled jobs older than 24 hours
        $oldJobs = ComparisonJob::where('updated_at', '<', now()->subDay())->pluck('id');
        $clearedIndexes = 0;
        foreach ($oldJobs as $jobId) {
            if ($indexManager->hasIndex($jobId)) {
                $indexManager->clearIndex($jobId);
                $clearedIndexes++;
            }
        }
        $this->info("Cleared {$clearedIndexes} expired ephemeral Redis indexes.");

        Log::info("Privacy Purge completed: {$purgedCount} docs, {$deletedFiles} files, {$clearedIndexes} indexes.");
        return Command::SUCCESS;
    }
}
