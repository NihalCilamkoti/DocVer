<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\ComparisonJob;
use App\Services\Comparison\DocumentChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends BaseApiController
{
    public function __construct(
        protected DocumentChatService $chatService
    ) {}

    public function index(ComparisonJob $job): JsonResponse
    {
        Gate::authorize('view', $job);

        $messages = $job->chatMessages()->get();

        return $this->successResponse(ChatMessageResource::collection($messages));
    }

    public function store(ChatMessageRequest $request, ComparisonJob $job): JsonResponse|StreamedResponse
    {
        Gate::authorize('view', $job);

        $query = $request->validated('message');

        // Store User query
        $userMsg = ChatMessage::create([
            'comparison_job_id' => $job->id,
            'user_id' => $request->user()->id,
            'sender' => 'user',
            'content' => $query,
        ]);

        // Check if streaming requested
        if ($request->boolean('stream') || $request->header('Accept') === 'text/event-stream') {
            return response()->stream(function () use ($job, $query, $request) {
                foreach ($this->chatService->streamAnswer($job, $query, $request->user()->id) as $chunk) {
                    echo "data: " . json_encode(['delta' => $chunk->delta, 'is_done' => $chunk->isDone]) . "\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
            ]);
        }

        // Generate evidence-backed answer with citations and follow-ups
        $aiResult = $this->chatService->answerQuery($job, $query, $request->user()->id);

        $aiMsg = ChatMessage::create([
            'comparison_job_id' => $job->id,
            'user_id' => null,
            'sender' => 'ai',
            'content' => $aiResult['content'],
            'citations' => $aiResult['citations'] ?? null,
            'follow_ups' => $aiResult['follow_ups'] ?? null,
        ]);

        if (!empty($aiResult['is_quota_error'])) {
            $aiMsg->is_quota_error = true;
            $aiMsg->quota_details = $aiResult['quota_details'] ?? null;
        }

        return $this->successResponse([
            'user_message' => new ChatMessageResource($userMsg),
            'ai_response' => new ChatMessageResource($aiMsg),
            'is_quota_error' => $aiResult['is_quota_error'] ?? false,
            'quota_details' => $aiResult['quota_details'] ?? null,
        ]);
    }

    public function clear(ComparisonJob $job): JsonResponse
    {
        Gate::authorize('view', $job);

        $job->chatMessages()->delete();

        return $this->successResponse([], 'Chat history cleared');
    }

    /**
     * Direct general-purpose conversational chat and document-assisted intelligence inquiry.
     */
    public function directChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
            'conversation_id' => ['nullable', 'string'],
            'comparison_job_id' => ['nullable'],
            'document_ids' => ['nullable', 'array'],
            'document_ids.*' => ['nullable'],
            'history' => ['nullable', 'array'],
        ]);

        $query = $validated['message'];
        $userId = $request->user()?->id;
        $jobId = $validated['comparison_job_id'] ?? null;
        $history = $validated['history'] ?? [];
        $docIds = $validated['document_ids'] ?? [];

        $job = null;
        if ($jobId) {
            $job = ComparisonJob::find($jobId);
        }

        $orchestrator = app(\App\Services\AI\AIOrchestrator::class);
        $aiResult = $orchestrator->answerDirectQuery(
            query: $query,
            userId: $userId,
            workspaceId: $job?->workspace_id,
            conversationHistory: $history,
            job: $job,
            documentIds: $docIds
        );

        return $this->successResponse([
            'user_message' => [
                'sender' => 'user',
                'content' => $query,
                'created_at' => now()->toIso8601String(),
            ],
            'ai_response' => [
                'sender' => 'ai',
                'content' => $aiResult['content'],
                'citations' => $aiResult['citations'] ?? [],
                'follow_ups' => $aiResult['follow_ups'] ?? [],
                'provider' => $aiResult['provider'] ?? 'groq',
                'model' => $aiResult['model'] ?? 'qwen',
                'intent' => $aiResult['intent'] ?? 'general_chat',
                'log_id' => $aiResult['log_id'] ?? null,
                'created_at' => now()->toIso8601String(),
            ],
            'is_quota_error' => $aiResult['is_quota_error'] ?? false,
            'quota_details' => $aiResult['quota_details'] ?? null,
        ]);
    }

    /**
     * Record thumbs up / down feedback for continuous evaluation and prompt tuning.
     */
    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'log_id' => ['nullable', 'integer'],
            'rating' => ['required', 'in:up,down'],
            'feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!empty($validated['log_id'])) {
            $logger = app(\App\Services\AI\Evaluation\AIEvaluationLogger::class);
            $logger->recordUserFeedback($validated['log_id'], $validated['rating'], $validated['feedback'] ?? null);
        }

        return $this->successResponse([], 'Feedback recorded successfully');
    }
}
