<?php

namespace App\Modules\Chat\Actions;

use App\Models\ComparisonJob;
use App\Services\Comparison\DocumentChatService;

class AskDocumentQuestionAction
{
    public function __construct(
        protected DocumentChatService $chatService
    ) {}

    /**
     * Query document context with ground truth citations and follow-up suggestions.
     */
    public function execute(ComparisonJob $job, string $query, ?int $userId = null): array
    {
        return $this->chatService->answerQuery($job, $query, $userId);
    }
}
