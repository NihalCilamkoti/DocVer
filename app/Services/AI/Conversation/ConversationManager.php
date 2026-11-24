<?php

namespace App\Services\AI\Conversation;

use App\Models\ChatMessage;
use App\Models\ComparisonJob;
use Illuminate\Support\Facades\Cache;

class ConversationManager
{
    /**
     * Retrieve recent conversation history for a comparison job, ensuring session isolation.
     *
     * @return array<array{sender: string, content: string}>
     */
    public function getComparisonHistory(int $comparisonJobId, int $maxTurns = 4): array
    {
        $messages = ChatMessage::where('comparison_job_id', $comparisonJobId)
            ->orderBy('id', 'desc')
            ->take($maxTurns * 2)
            ->get()
            ->reverse();

        $history = [];
        foreach ($messages as $msg) {
            $history[] = [
                'sender' => $msg->sender === 'ai' ? 'assistant' : 'user',
                'content' => $msg->content,
            ];
        }

        return $history;
    }

    /**
     * Retrieve ephemeral session history for a standalone document query.
     */
    public function getDocumentHistory(int $documentId, ?int $userId, int $maxTurns = 4): array
    {
        $key = "doc_chat_history:{$documentId}:" . ($userId ?? 'guest');
        return Cache::get($key, []);
    }

    /**
     * Append to ephemeral session history for a standalone document.
     */
    public function recordDocumentTurn(int $documentId, ?int $userId, string $userQuery, string $aiAnswer): void
    {
        $key = "doc_chat_history:{$documentId}:" . ($userId ?? 'guest');
        $history = Cache::get($key, []);

        $history[] = ['sender' => 'user', 'content' => $userQuery];
        $history[] = ['sender' => 'assistant', 'content' => $aiAnswer];

        // Keep last 8 messages
        $history = array_slice($history, -8);
        Cache::put($key, $history, 3600); // 1 hour TTL
    }

    /**
     * Clear document conversation history.
     */
    public function clearDocumentHistory(int $documentId, ?int $userId): void
    {
        $key = "doc_chat_history:{$documentId}:" . ($userId ?? 'guest');
        Cache::forget($key);
    }
}
