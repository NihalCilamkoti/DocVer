<?php

namespace App\Jobs;

use App\Services\Documents\DocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PurgeExpiredDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(DocumentService $documentService): void
    {
        $purgedCount = $documentService->purgeExpiredDocuments();
        Log::info("PurgeExpiredDocumentsJob executed. Purged {$purgedCount} expired documents.");
    }
}
