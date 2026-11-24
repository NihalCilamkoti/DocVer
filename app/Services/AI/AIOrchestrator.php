<?php

namespace App\Services\AI;

use App\Models\ComparisonJob;
use App\Models\DocumentMetadata;
use App\Services\AI\Config\TokenManager;
use App\Services\AI\Context\ContextBuilder;
use App\Services\AI\Conversation\ConversationManager;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\Intent\TaskIntentClassifier;
use App\Services\AI\Intent\TaskIntentDTO;
use App\Services\AI\Prompts\PromptManager;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\AI\Quota\AIQuotaResolver;
use App\Services\AI\Validation\ResponseValidator;
use Generator;
use Illuminate\Support\Facades\Log;

class AIOrchestrator
{
    public function __construct(
        protected TaskIntentClassifier $intentClassifier,
        protected ContextBuilder $contextBuilder,
        protected PromptManager $promptManager,
        protected TokenManager $tokenManager,
        protected ResponseValidator $responseValidator,
        protected ConversationManager $conversationManager,
        protected AIProviderRouter $aiRouter,
        protected AIUsageTracker $usageTracker,
        protected ?AIQuotaResolver $quotaResolver = null,
        protected ?\App\Services\AI\Evaluation\AIEvaluationLogger $evaluationLogger = null
    ) {
        $this->quotaResolver = $this->quotaResolver ?? app(AIQuotaResolver::class);
        $this->evaluationLogger = $this->evaluationLogger ?? app(\App\Services\AI\Evaluation\AIEvaluationLogger::class);
    }

    /**
     * Answer a comparison chat / verification query with full AI orchestration.
     */
    public function answerComparisonQuery(ComparisonJob $job, string $query, ?int $userId = null): array
    {
        $startTime = microtime(true);

        // 1. Task-Intent Identification & Scope Understanding
        $intent = $this->intentClassifier->classify($query);

        // 2. Build Section-Aware Context
        $context = $this->contextBuilder->buildComparisonContext($job, $intent);

        // 3. Retrieve Conversation History for Session
        $history = $this->conversationManager->getComparisonHistory($job->id, 3);

        $docAName = $job->documentA?->original_name ?? 'Document A';
        $docBName = $job->documentB?->original_name ?? 'Document B';

        // MODE F: General Conversational Chat (no document context forced)
        if ($intent->isGeneralChat()) {
            return $this->executeGeneralChat($query, $intent, $job, $userId);
        }

        // MODE R: Direct Role & Task Extraction (Dedicated extractor for accurate names, joint assignments, titles vs responsibilities)
        if ($intent->isRoleOrTaskExtraction()) {
            $indexManager = app(\App\Services\Documents\Indexing\EphemeralIndexManager::class);
            $chunksA = $indexManager->getChunks($job->id, 'A');
            $chunksB = $indexManager->getChunks($job->id, 'B');
            $targetChunks = $intent->targetSlot === 'A' ? $chunksA : $chunksB;
            if (empty($targetChunks)) {
                $targetChunks = !empty($chunksB) ? $chunksB : $chunksA;
            }
            $targetDocName = ($intent->targetSlot === 'A') ? $docAName : $docBName;

            if (!empty($targetChunks)) {
                $roleService = app(\App\Services\Comparison\RoleExtractionService::class);
                $roleData = $roleService->extractRolesFromChunks($targetChunks, $intent->targetSlot === 'A' ? 'A' : 'B', $targetDocName);
                $directAnswer = $roleService->answerRoleQuery($query, $roleData, $targetDocName);

                if ($directAnswer !== null) {
                    $citations = $directAnswer['citation'] ? [$directAnswer['citation']] : [];
                    return [
                        'content' => $directAnswer['direct_answer'],
                        'citations' => $citations,
                        'follow_ups' => [
                            'Who handles product responsibilities?',
                            'How many members are there?',
                            'Give me individual roles and how many members are there.',
                        ],
                        'provider' => 'deterministic_nlp',
                        'model' => 'role_extraction_engine',
                        'intent' => 'role_and_task_extraction',
                        'relevance_score' => 1.0,
                        'is_grounded' => true,
                        'is_quota_error' => false,
                        'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                    ];
                }
            }
        }

        // MODE D: User-Friendly General Comparison Summary (simple language, table, no unsolicited legal boilerplate)
        if ($intent->isClauseDiff() && empty($intent->targetClauses) && !$intent->isAuditReportRequest()) {
            $indexManager = app(\App\Services\Documents\Indexing\EphemeralIndexManager::class);
            $chunksA = $indexManager->getChunks($job->id, 'A');
            $chunksB = $indexManager->getChunks($job->id, 'B');
            if (!empty($chunksA) && !empty($chunksB)) {
                $structureAnalyzer = app(\App\Services\Comparison\DocumentStructureAnalyzer::class);
                $sectionsA = $structureAnalyzer->analyzeStructure($chunksA, 'A');
                $sectionsB = $structureAnalyzer->analyzeStructure($chunksB, 'B');
                $relClassifier = app(\App\Services\Comparison\DocumentRelationshipClassifier::class);
                $relationship = $relClassifier->classify($sectionsA, $sectionsB, $docAName, $docBName, $query);

                if ($relationship->isDifferentPurposes()) {
                    $content = $this->buildUserFriendlyComparisonSummary($docAName, $docBName, $relationship);
                    return [
                        'content' => $content,
                        'citations' => [
                            ['docLabel' => 'Document A', 'page' => 1, 'quote' => !empty($sectionsA) ? mb_substr($sectionsA[0]->content, 0, 120) . '...' : $docAName],
                            ['docLabel' => 'Document B', 'page' => 1, 'quote' => !empty($sectionsB) ? mb_substr($sectionsB[0]->content, 0, 120) . '...' : $docBName],
                        ],
                        'follow_ups' => [
                            'Who is taking the technical role in Document 2?',
                            'Give me individual roles and how many members are there.',
                            'What are the key schedules in Document 1?',
                        ],
                        'provider' => 'system_comparator',
                        'model' => 'relationship_classifier',
                        'intent' => 'clause_diff',
                        'relevance_score' => 1.0,
                        'is_grounded' => true,
                        'is_quota_error' => false,
                        'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                    ];
                }
            }
        }

        // MODE B: Multiple Document Summarization (summarize both independently)
        if ($intent->isMultiDocSummary() && $job->documentA && $job->documentB) {
            return $this->executeMultiDocumentSummary($job, $query, $intent, $userId);
        }

        // 2. Build Section-Aware Context
        $prompts = $this->promptManager->buildPrompt(
            intent: $intent,
            context: $context,
            docAName: $docAName,
            docBName: $docBName,
            conversationHistory: $history
        );

        // 5. Configure Task-Optimal Parameters (0.0 for comparison/numbers, 0.15 for QA)
        $temperature = $this->tokenManager->getTemperatureForIntent($intent);
        $maxTokens = $this->tokenManager->getMaxTokensForIntent($intent);

        $aiRequest = new AIRequest(
            prompt: $prompts['userPrompt'],
            systemInstruction: $prompts['systemInstruction'],
            temperature: $temperature,
            maxTokens: $maxTokens,
            taskType: 'chat',
            userId: $userId,
            workspaceId: $job->workspace_id
        );

        $rawContent = '';
        $providerName = 'groq';
        $modelName = 'qwen/qwen3.8-27b';

        try {
            $response = $this->aiRouter->route($aiRequest);
            $rawContent = $response->content;
            $providerName = $response->provider;
            $modelName = $response->model;
        } catch (\Throwable $e) {
            Log::warning("AI Provider execution failed: " . $e->getMessage());

            if (!$context->targetFound && !empty($intent->targetClauses) && !($e instanceof \App\Services\AI\Exceptions\QuotaExceededException || $e instanceof \App\Services\AI\Exceptions\AllProvidersUnavailableException)) {
                $targetClause = implode(', ', $intent->targetClauses);
                $rawContent = "Neither Document A ({$docAName}) nor Document B ({$docBName}) contains provisions or sections regarding **{$targetClause}**.\n\n" .
                    "The documents focus on other operative areas: " . implode(', ', array_slice($context->availableSections, 0, 5)) . ".";
            } else {
                $quotaResult = $this->quotaResolver->resolveGeneralError($e, $providerName ?? null);
                return [
                    'content' => $quotaResult->friendlyMessage,
                    'citations' => [],
                    'follow_ups' => ['Try again shortly', 'Check AI usage in settings'],
                    'provider' => $providerName ?? 'system',
                    'model' => $modelName ?? 'none',
                    'intent' => $intent->intent,
                    'relevance_score' => 1.0,
                    'is_grounded' => true,
                    'is_quota_error' => true,
                    'quota_details' => $quotaResult->toArray()['quota_details'],
                    'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                ];
            }
        }

        // 6. Response Quality & Grounding Validation Layer
        $validation = $this->responseValidator->validate(
            aiContent: $rawContent,
            intent: $intent,
            context: $context,
            docAName: $docAName,
            docBName: $docBName
        );

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $followUps = $this->generateFollowUps($intent, $query);

        $evalLog = $this->evaluationLogger->logInteraction(
            prompt: $query,
            intent: $intent,
            conversationHistory: $history,
            documentReferences: [
                ['id' => $job->documentA?->id, 'name' => $docAName, 'slot' => 'A'],
                ['id' => $job->documentB?->id, 'name' => $docBName, 'slot' => 'B'],
            ],
            responseContent: $validation->validatedContent,
            provider: $providerName,
            model: $modelName,
            groundingScore: $validation->relevanceScore,
            isSuccessful: true,
            errorCategory: null,
            durationMs: $durationMs,
            userId: $userId,
            workspaceId: $job->workspace_id,
            comparisonJobId: $job->id
        );

        return [
            'content' => $validation->validatedContent,
            'citations' => $validation->validatedCitations,
            'follow_ups' => $followUps,
            'provider' => $providerName,
            'model' => $modelName,
            'intent' => $intent->intent,
            'relevance_score' => $validation->relevanceScore,
            'is_grounded' => $validation->isGrounded,
            'duration_ms' => $durationMs,
            'log_id' => $evalLog?->id,
        ];
    }

    /**
     * Answer a single document query with full AI orchestration.
     */
    public function answerDocumentQuery(DocumentMetadata $document, string $query, ?int $userId = null): array
    {
        $startTime = microtime(true);

        // 1. Task-Intent Identification
        $intent = $this->intentClassifier->classify($query);

        // MODE R: Direct Role & Task Extraction on Single Document
        if ($intent->isRoleOrTaskExtraction()) {
            $indexManager = app(\App\Services\Documents\Indexing\EphemeralIndexManager::class);
            $chunks = $indexManager->getChunks($document->id, $document->slot ?? 'A');
            if (empty($chunks) && $document->comparison_job_id) {
                $chunks = $indexManager->getChunks($document->comparison_job_id, $document->slot ?? 'A');
            }
            if (!empty($chunks)) {
                $roleService = app(\App\Services\Comparison\RoleExtractionService::class);
                $roleData = $roleService->extractRolesFromChunks($chunks, $document->slot ?? 'A', $document->original_name);
                $directAnswer = $roleService->answerRoleQuery($query, $roleData, $document->original_name);

                if ($directAnswer !== null) {
                    $citations = $directAnswer['citation'] ? [$directAnswer['citation']] : [];
                    return [
                        'content' => $directAnswer['direct_answer'],
                        'citations' => $citations,
                        'follow_ups' => [
                            'Who handles product responsibilities?',
                            'How many members are there?',
                            'Give me individual roles and how many members are there.',
                        ],
                        'provider' => 'deterministic_nlp',
                        'model' => 'role_extraction_engine',
                        'intent' => 'role_and_task_extraction',
                        'relevance_score' => 1.0,
                        'is_grounded' => true,
                        'is_quota_error' => false,
                        'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                    ];
                }
            }
        }

        // 2. Build Section-Aware Context
        $context = $this->contextBuilder->buildSingleDocumentContext($document, $intent);

        // 3. Retrieve Conversation History
        $history = $this->conversationManager->getDocumentHistory($document->id, $userId, 3);

        // 4. Assemble Specialized Task Prompt
        $prompts = $this->promptManager->buildPrompt(
            intent: $intent,
            context: $context,
            docAName: $document->original_name,
            docBName: '',
            conversationHistory: $history
        );

        // 5. Configure Parameters
        $temperature = $this->tokenManager->getTemperatureForIntent($intent);
        $maxTokens = $this->tokenManager->getMaxTokensForIntent($intent);

        $aiRequest = new AIRequest(
            prompt: $prompts['userPrompt'],
            systemInstruction: $prompts['systemInstruction'],
            temperature: $temperature,
            maxTokens: $maxTokens,
            taskType: 'chat',
            userId: $userId,
            workspaceId: $document->workspace_id
        );

        $rawContent = '';
        $providerName = 'groq';
        $modelName = 'qwen/qwen3.8-27b';

        try {
            $response = $this->aiRouter->route($aiRequest);
            $rawContent = $response->content;
            $providerName = $response->provider;
            $modelName = $response->model;
        } catch (\Throwable $e) {
            Log::warning("Single-doc AI query failed: " . $e->getMessage());

            if (!$context->targetFound && !empty($intent->targetClauses) && !($e instanceof \App\Services\AI\Exceptions\QuotaExceededException || $e instanceof \App\Services\AI\Exceptions\AllProvidersUnavailableException)) {
                $targetClause = implode(', ', $intent->targetClauses);
                $rawContent = "The document \"{$document->original_name}\" does not contain any provisions, mentions, or sections regarding **{$targetClause}**.\n\n" .
                    "Available sections: " . implode(', ', array_slice($context->availableSections, 0, 5)) . ".";
            } else {
                $quotaResult = $this->quotaResolver->resolveGeneralError($e, $providerName ?? null);
                return [
                    'document_id' => $document->id,
                    'document_name' => $document->original_name,
                    'page_count' => $document->page_count ?? 1,
                    'prompt' => $query,
                    'answer' => $quotaResult->friendlyMessage,
                    'citations' => [],
                    'follow_ups' => ['Try again shortly', 'Check usage'],
                    'provider' => $providerName ?? 'system',
                    'model' => $modelName ?? 'none',
                    'intent' => $intent->intent,
                    'relevance_score' => 1.0,
                    'is_grounded' => true,
                    'is_quota_error' => true,
                    'quota_details' => $quotaResult->toArray()['quota_details'],
                    'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
                ];
            }
        }

        // 6. Validate Response
        $validation = $this->responseValidator->validate(
            aiContent: $rawContent,
            intent: $intent,
            context: $context,
            docAName: $document->original_name
        );

        // Record turn in session memory
        $this->conversationManager->recordDocumentTurn($document->id, $userId, $query, $validation->validatedContent);

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $followUps = $this->generateFollowUps($intent, $query);

        $evalLog = $this->evaluationLogger->logInteraction(
            prompt: $query,
            intent: $intent,
            conversationHistory: $history,
            documentReferences: [
                ['id' => $document->id, 'name' => $document->original_name, 'slot' => $document->slot],
            ],
            responseContent: $validation->validatedContent,
            provider: $providerName,
            model: $modelName,
            groundingScore: $validation->relevanceScore,
            isSuccessful: true,
            errorCategory: null,
            durationMs: $durationMs,
            userId: $userId,
            workspaceId: $document->workspace_id,
            comparisonJobId: $document->comparison_job_id
        );

        return [
            'document_id' => $document->id,
            'document_name' => $document->original_name,
            'page_count' => $document->page_count ?? 1,
            'prompt' => $query,
            'content' => $validation->validatedContent,
            'answer' => $validation->validatedContent,
            'citations' => $validation->validatedCitations,
            'follow_ups' => $followUps,
            'provider' => $providerName,
            'model' => $modelName,
            'intent' => $intent->intent,
            'relevance_score' => $validation->relevanceScore,
            'duration_ms' => $durationMs,
            'log_id' => $evalLog?->id,
        ];
    }

    /**
     * Generate an executive cross-comparison synthesis.
     */
    public function generateExecutiveReport(ComparisonJob $job, int $similarityScore, array $findings): ?string
    {
        $focusPrompt = $job->metadata['focus_prompt'] ?? null;
        $intent = $focusPrompt
            ? $this->intentClassifier->classify($focusPrompt)
            : $this->intentClassifier->classify("Summarize key differences and provisions between Document A and Document B");

        $docAName = $job->documentA?->original_name ?? 'Document A';
        $docBName = $job->documentB?->original_name ?? 'Document B';

        // Check if documents serve different purposes
        if (($job->metadata['document_relationship'] ?? '') === 'different_purposes') {
            $indexManager = app(\App\Services\Documents\Indexing\EphemeralIndexManager::class);
            $chunksA = $indexManager->getChunks($job->id, 'A');
            $chunksB = $indexManager->getChunks($job->id, 'B');
            if (!empty($chunksA) && !empty($chunksB)) {
                $structureAnalyzer = app(\App\Services\Comparison\DocumentStructureAnalyzer::class);
                $sectionsA = $structureAnalyzer->analyzeStructure($chunksA, 'A');
                $sectionsB = $structureAnalyzer->analyzeStructure($chunksB, 'B');
                $relClassifier = app(\App\Services\Comparison\DocumentRelationshipClassifier::class);
                $relationship = $relClassifier->classify($sectionsA, $sectionsB, $docAName, $docBName, $focusPrompt ?? '');
                return $this->buildUserFriendlyComparisonSummary($docAName, $docBName, $relationship);
            }
        }

        if (app()->runningUnitTests() && !config('ai.test_live_ai', false)) {
            return "Executive Comparison Synthesis between {$docAName} and {$docBName} (Similarity: {$similarityScore}%).";
        }

        $context = $this->contextBuilder->buildComparisonContext($job, $intent);

        $prompt = "Document A: {$docAName}\nDocument B: {$docBName}\n\n" .
            "Context Excerpts:\n{$context->formattedContext}\n\n" .
            ($focusPrompt ? "User Focus Request: {$focusPrompt}\n\n" : "User Request: Compare Document A and Document B.\n\n") .
            "Similarity Score: {$similarityScore}%\n" .
            "Detected Findings Count: " . count($findings) . "\n\n" .
            "Provide a clear Executive Cross-Comparison answering the user focus request directly, followed by key itemized variances.";

        $systemInstruction = "You are DocVer's AI Cross-Comparison Specialist. Contrast Document A and Document B based strictly on the provided texts. Directly answer the user prompt with an executive comparison and itemized variances in concise, clear markdown.";

        try {
            $aiRequest = new AIRequest(
                prompt: $prompt,
                systemInstruction: $systemInstruction,
                temperature: 0.1,
                maxTokens: 2500,
                taskType: 'comparison',
                userId: $job->user_id,
                workspaceId: $job->workspace_id
            );

            $response = $this->aiRouter->route($aiRequest);
            return $response->content;
        } catch (\Throwable $e) {
            Log::warning("AI Executive Summary generation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Build clean, user-friendly comparison summary for different-purpose documents.
     */
    public function buildUserFriendlyComparisonSummary(
        string $docAName,
        string $docBName,
        \App\Services\Comparison\DTOs\DocumentRelationshipDTO $relationship
    ): string {
        $simList = !empty($relationship->similarities)
            ? implode("\n", array_map(fn($s) => "- {$s}", $relationship->similarities))
            : "- No substantive structural overlap was identified between the two documents.";

        $diffList = !empty($relationship->differences)
            ? implode("\n", array_map(fn($d) => "- {$d}", $relationship->differences))
            : "- Document A and Document B address distinct topics.";

        $uniqueAList = !empty($relationship->uniqueA)
            ? implode("\n", array_map(fn($u) => "- {$u}", $relationship->uniqueA))
            : "- Primary provisions specific to {$docAName}.";

        $uniqueBList = !empty($relationship->uniqueB)
            ? implode("\n", array_map(fn($u) => "- {$u}", $relationship->uniqueB))
            : "- Specific content unique to {$docBName}.";

        $simScore = (int) round($relationship->textualOverlap * 100);
        $topicScore = (int) round($relationship->topicSimilarity * 100);
        $structuralScore = (int) round($relationship->structuralSimilarity * 100);
        $explanation = $relationship->similarityExplanation ?: "The {$simScore}% textual score reflects distinct vocabularies between {$docAName} and {$docBName}.";

        return <<<SUMMARY
### 1. Documents Compared
- **Document A:** {$docAName}
- **Document B:** {$docBName}

### 2. Overall Difference
{$relationship->relationshipSummary}

### 3. Main Differences
{$diffList}

### 4. Important Information Unique to Each Document
**Unique to {$docAName}:**
{$uniqueAList}

**Unique to {$docBName}:**
{$uniqueBList}

### 5. Similarities
{$simList}

### 6. Similarity Analysis
- **Textual Overlap:** {$simScore}%
- **Structural Heading Alignment:** {$structuralScore}%
- **Topic Domain Match:** {$topicScore}%
*Score Explanation:* {$explanation}

### 7. Important Conclusion
{$docAName} and {$docBName} serve entirely different purposes and are independent documents rather than revisions of one another.
SUMMARY;
    }

    /**
     * Stream answer chunk by chunk via generator.
     */
    public function streamComparisonQuery(ComparisonJob $job, string $query, ?int $userId = null): Generator
    {
        $intent = $this->intentClassifier->classify($query);
        $context = $this->contextBuilder->buildComparisonContext($job, $intent);

        $docAName = $job->documentA?->original_name ?? 'Document A';
        $docBName = $job->documentB?->original_name ?? 'Document B';

        $prompts = $this->promptManager->buildPrompt($intent, $context, $docAName, $docBName);

        $aiRequest = new AIRequest(
            prompt: $prompts['userPrompt'],
            systemInstruction: $prompts['systemInstruction'],
            temperature: 0.1,
            stream: true,
            taskType: 'chat',
            userId: $userId,
            workspaceId: $job->workspace_id
        );

        yield from $this->aiRouter->routeStream($aiRequest);
    }

    /**
     * Execute General Chat without forcing document context (conversational Q&A).
     */
    protected function executeGeneralChat(string $query, TaskIntentDTO $intent, ComparisonJob $job, ?int $userId): array
    {
        $startTime = microtime(true);
        $history = $this->conversationManager->getComparisonHistory($job->id, 3);
        $historyText = $this->promptManager->formatConversationHistory($history);
        $prompts = $this->promptManager->buildGeneralChatPrompt($query, $historyText, $intent);

        $formatRules = $this->promptManager->buildConstraintDirectives($intent);
        if (!empty($formatRules)) {
            $prompts['systemInstruction'] .= "\n\nSTRICT OUTPUT FORMAT & CONSTRAINT MANDATES:\n" . $formatRules;
        }

        $aiRequest = new AIRequest(
            prompt: $prompts['userPrompt'],
            systemInstruction: $prompts['systemInstruction'],
            temperature: 0.2,
            maxTokens: 1000,
            taskType: 'chat',
            userId: $userId,
            workspaceId: $job->workspace_id
        );

        try {
            $response = $this->aiRouter->route($aiRequest);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $validation = $this->responseValidator->validate(
                aiContent: $response->content,
                intent: $intent,
                context: new \App\Services\AI\Context\ContextResultDTO(),
                docAName: '',
                docBName: ''
            );

            $followUps = $this->generateFollowUps($intent, $query);

            return [
                'content' => $validation->validatedContent,
                'citations' => [],
                'follow_ups' => $followUps,
                'provider' => $response->provider,
                'model' => $response->model,
                'intent' => 'general_chat',
                'relevance_score' => 1.0,
                'is_grounded' => true,
                'duration_ms' => $durationMs,
            ];
        } catch (\Throwable $e) {
            $quotaResult = $this->quotaResolver->resolveGeneralError($e);
            return [
                'content' => $quotaResult->friendlyMessage,
                'citations' => [],
                'follow_ups' => ['Try again shortly', 'Check usage in settings'],
                'provider' => 'system',
                'model' => 'none',
                'intent' => 'general_chat',
                'relevance_score' => 1.0,
                'is_grounded' => true,
                'is_quota_error' => true,
                'quota_details' => $quotaResult->toArray()['quota_details'],
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ];
        }
    }

    /**
     * Execute Multiple Document Summarization with Strict Document Isolation (8-step pipeline).
     */
    protected function executeMultiDocumentSummary(ComparisonJob $job, string $query, TaskIntentDTO $intent, ?int $userId): array
    {
        $startTime = microtime(true);
        $docA = $job->documentA;
        $docB = $job->documentB;

        $docAName = $docA?->original_name ?? 'Document 1';
        $docBName = $docB?->original_name ?? 'Document 2';

        // 1. Build Isolated Context for Document A (Doc A ONLY)
        $intentA = new TaskIntentDTO(
            intent: 'summarization',
            rawQuery: $query,
            targetSlot: 'A',
            scope: 'single_document'
        );
        $contextA = $this->contextBuilder->buildComparisonContext($job, $intentA);

        // 2. Build Isolated Context for Document B (Doc B ONLY)
        $intentB = new TaskIntentDTO(
            intent: 'summarization',
            rawQuery: $query,
            targetSlot: 'B',
            scope: 'single_document'
        );
        $contextB = $this->contextBuilder->buildComparisonContext($job, $intentB);

        // 3. Generate Document A summary with isolated prompt
        $promptA = $this->promptManager->buildIsolatedSingleDocSummaryPrompt($docAName, '1', $contextA->formattedContext, $query);
        $reqA = new AIRequest(
            prompt: $promptA['userPrompt'],
            systemInstruction: $promptA['systemInstruction'],
            temperature: 0.1,
            maxTokens: 1500,
            taskType: 'chat',
            userId: $userId,
            workspaceId: $job->workspace_id
        );

        // 4. Generate Document B summary with isolated prompt
        $promptB = $this->promptManager->buildIsolatedSingleDocSummaryPrompt($docBName, '2', $contextB->formattedContext, $query);
        $reqB = new AIRequest(
            prompt: $promptB['userPrompt'],
            systemInstruction: $promptB['systemInstruction'],
            temperature: 0.1,
            maxTokens: 1500,
            taskType: 'chat',
            userId: $userId,
            workspaceId: $job->workspace_id
        );

        try {
            $resA = $this->aiRouter->route($reqA);
            $resB = $this->aiRouter->route($reqB);
        } catch (\Throwable $e) {
            $quotaResult = $this->quotaResolver->resolveGeneralError($e);
            return [
                'content' => $quotaResult->friendlyMessage,
                'citations' => [],
                'follow_ups' => ['Try again shortly', 'Check usage in settings'],
                'provider' => 'system',
                'model' => 'none',
                'intent' => 'multi_document_summary',
                'relevance_score' => 1.0,
                'is_grounded' => true,
                'is_quota_error' => true,
                'quota_details' => $quotaResult->toArray()['quota_details'],
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ];
        }

        // 5. Cross-Attribution & Grounding Validation
        $valA = $this->responseValidator->validateIsolatedSummary($resA->content, $contextA, $contextB, $docAName, '1');
        $valB = $this->responseValidator->validateIsolatedSummary($resB->content, $contextB, $contextA, $docBName, '2');

        // 6. Combine into structured, isolated response
        $combinedContent = "### 📄 Document 1: {$docAName}\n\n" .
            $valA->validatedContent . "\n\n" .
            "---\n\n" .
            "### 📄 Document 2: {$docBName}\n\n" .
            $valB->validatedContent;

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $citations = array_merge($contextA->citations, $contextB->citations);

        return [
            'content' => $combinedContent,
            'citations' => $citations,
            'follow_ups' => [
                'Compare these two documents',
                'Which document has a higher liability limit?',
                'Make the first summary shorter',
            ],
            'provider' => $resA->provider,
            'model' => $resA->model,
            'intent' => 'multi_document_summary',
            'relevance_score' => 1.0,
            'is_grounded' => true,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Direct general chat or document-assisted chat without requiring an existing comparison job.
     */
    public function answerDirectQuery(
        string $query,
        ?int $userId = null,
        ?int $workspaceId = null,
        array $conversationHistory = [],
        ?ComparisonJob $job = null,
        ?array $documentIds = null
    ): array {
        $startTime = microtime(true);
        $intent = $this->intentClassifier->classify($query);

        // 1. Authoritative Platform Upload Limit Inquiry (BUG 1 FIX)
        if ($intent->intent === 'upload_inquiry') {
            $uploadService = app(\App\Services\Documents\UploadCapabilityService::class);
            $user = $userId ? \App\Models\User::find($userId) : null;
            $workspace = $workspaceId ? \App\Models\Workspace::find($workspaceId) : null;
            $content = $uploadService->getUploadLimitsSummary($user, $workspace);

            return [
                'content' => $content,
                'citations' => [],
                'follow_ups' => [
                    'What file formats are supported?',
                    'What are the plan storage limits?',
                    'How do I attach a file to the chat?',
                ],
                'provider' => 'system',
                'model' => 'docver-platform',
                'intent' => 'upload_inquiry',
                'relevance_score' => 1.0,
                'is_grounded' => true,
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ];
        }

        // 2. Resolve Valid Attached Documents
        $resolvedDocs = collect($documentIds ?? [])
            ->map(function ($id) {
                if (is_numeric($id)) {
                    return DocumentMetadata::find((int) $id);
                }
                return null;
            })
            ->filter()
            ->values();

        // If target slot B is specified and document B exists
        if ($intent->targetSlot === 'B' && $resolvedDocs->isNotEmpty() && !$intent->isGeneralChat()) {
            $docB = $resolvedDocs->firstWhere('slot', 'B') ?? ($resolvedDocs->get(1) ?? $resolvedDocs->first());
            if ($docB) {
                return $this->answerDocumentQuery($docB, $query, $userId);
            }
        }

        // If target slot A is specified and document A exists
        if ($intent->targetSlot === 'A' && $resolvedDocs->isNotEmpty() && !$intent->isGeneralChat()) {
            $docA = $resolvedDocs->firstWhere('slot', 'A') ?? $resolvedDocs->first();
            if ($docA) {
                return $this->answerDocumentQuery($docA, $query, $userId);
            }
        }

        // Auto-link comparison job if 2 documents are present
        if ($job === null && $resolvedDocs->count() >= 2 && !$intent->isGeneralChat()) {
            $docA = $resolvedDocs->firstWhere('slot', 'A') ?? $resolvedDocs->get(0);
            $docB = $resolvedDocs->firstWhere('slot', 'B') ?? $resolvedDocs->get(1);
            if ($docA && $docB) {
                $job = ComparisonJob::whereHas('documents', function ($q) use ($docA) {
                    $q->where('id', $docA->id);
                })->whereHas('documents', function ($q) use ($docB) {
                    $q->where('id', $docB->id);
                })->latest()->first();

                if (!$job) {
                    $ws = $workspaceId ? \App\Models\Workspace::find($workspaceId) : ($docA->workspace ?? \App\Models\Workspace::first());
                    if ($ws) {
                        $job = ComparisonJob::create([
                            'workspace_id' => $ws->id,
                            'user_id' => $userId,
                            'title' => "{$docA->original_name} vs {$docB->original_name}",
                            'status' => 'completed',
                            'similarity_score' => 85,
                            'metadata' => ['focus_prompt' => $query],
                        ]);
                        $docA->update(['comparison_job_id' => $job->id, 'slot' => 'A']);
                        $docB->update(['comparison_job_id' => $job->id, 'slot' => 'B']);
                    }
                }
            }
        }

        // If a comparison job exists and the query is a document query, delegate
        if ($job !== null && !$intent->isGeneralChat()) {
            return $this->answerComparisonQuery($job, $query, $userId);
        }

        // Check if single document query
        if ($resolvedDocs->count() === 1 && !$intent->isGeneralChat()) {
            return $this->answerDocumentQuery($resolvedDocs->first(), $query, $userId);
        }

        // Execute general conversational AI
        $historyText = '';
        if (!empty($conversationHistory)) {
            $historyLines = [];
            foreach (array_slice($conversationHistory, -5) as $turn) {
                $role = ($turn['sender'] ?? 'user') === 'user' ? 'User' : 'Assistant';
                $historyLines[] = "{$role}: " . ($turn['content'] ?? '');
            }
            $historyText = implode("\n", $historyLines) . "\n\n";
        }

        $prompts = $this->promptManager->buildGeneralChatPrompt($query, $historyText, $intent);

        $formatRules = $this->promptManager->buildConstraintDirectives($intent);
        if (!empty($formatRules)) {
            $prompts['systemInstruction'] .= "\n\nSTRICT OUTPUT FORMAT & CONSTRAINT MANDATES:\n" . $formatRules;
        }

        $temperature = $intent->hasConstraint('direct_factual_answer') ? 0.0 : 0.3;
        $maxTokens = 1500;

        $aiRequest = new AIRequest(
            prompt: $prompts['userPrompt'],
            systemInstruction: $prompts['systemInstruction'],
            temperature: $temperature,
            maxTokens: $maxTokens,
            taskType: 'chat',
            userId: $userId,
            workspaceId: $workspaceId
        );

        $providerName = 'groq';
        $modelName = 'qwen/qwen3.8-27b';

        try {
            $response = $this->aiRouter->route($aiRequest);
            $providerName = $response->provider;
            $modelName = $response->model;
            $rawContent = $response->content;
        } catch (\Throwable $e) {
            $quotaResult = $this->quotaResolver->resolveGeneralError($e);
            return [
                'content' => $quotaResult->friendlyMessage,
                'citations' => [],
                'follow_ups' => ['Try again shortly', 'Check usage in settings'],
                'provider' => 'system',
                'model' => 'none',
                'intent' => 'general_chat',
                'relevance_score' => 1.0,
                'is_grounded' => true,
                'is_quota_error' => true,
                'quota_details' => $quotaResult->toArray()['quota_details'],
                'duration_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ];
        }

        $validation = $this->responseValidator->validate(
            aiContent: $rawContent,
            intent: $intent,
            context: new \App\Services\AI\Context\ContextResultDTO(),
            docAName: '',
            docBName: ''
        );

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $followUps = $this->generateFollowUps($intent, $query);

        $evalLog = $this->evaluationLogger->logInteraction(
            prompt: $query,
            intent: $intent,
            conversationHistory: $conversationHistory,
            documentReferences: $resolvedDocs->map(fn($d) => ['id' => $d->id, 'name' => $d->original_name, 'slot' => $d->slot])->toArray(),
            responseContent: $validation->validatedContent,
            provider: $providerName,
            model: $modelName,
            groundingScore: 1.0,
            isSuccessful: true,
            errorCategory: null,
            durationMs: $durationMs,
            userId: $userId,
            workspaceId: $workspaceId,
            comparisonJobId: $job?->id
        );

        return [
            'content' => $validation->validatedContent,
            'citations' => [],
            'follow_ups' => $followUps,
            'provider' => $providerName,
            'model' => $modelName,
            'intent' => $intent->intent,
            'relevance_score' => 1.0,
            'is_grounded' => true,
            'duration_ms' => $durationMs,
            'log_id' => $evalLog?->id,
        ];
    }

    /**
     * Generate dynamic follow-up suggestions grounded in user intent.
     */
    protected function generateFollowUps(TaskIntentDTO $intent, string $query): array
    {
        if ($intent->isGeneralChat() || $intent->intent === 'general_chat') {
            $lower = strtolower($query);
            if (str_contains($lower, 'python') || str_contains($lower, 'code') || str_contains($lower, 'program')) {
                return [
                    'Can you provide another code example?',
                    'How do I test this in Python?',
                    'Explain edge cases and potential errors',
                ];
            }
            if (str_contains($lower, 'email') || str_contains($lower, 'letter') || str_contains($lower, 'write')) {
                return [
                    'Make it more concise',
                    'Adjust the tone to be more casual',
                    'Add a clear subject line and call to action',
                ];
            }
            if (str_contains($lower, 'project') || str_contains($lower, 'idea') || str_contains($lower, 'brainstorm')) {
                return [
                    'Expand on the first idea',
                    'Which idea is best for a beginner?',
                    'Give me 5 more variations',
                ];
            }
            if (str_contains($lower, 'where') || str_contains($lower, 'capital') || str_contains($lower, 'who') || str_contains($lower, 'when')) {
                return [
                    'Tell me more about its history',
                    'What are other key facts?',
                    'Explain in simple language',
                ];
            }
            return [
                'Can you elaborate on that?',
                'Explain this in simpler terms',
                'Give a concrete example',
            ];
        }

        if ($intent->intent === 'clause_diff') {
            if ($intent->hasConstraint('simple_language') || $intent->hasConstraint('general_comparison')) {
                return [
                    'Show the differences in a table',
                    'Compare only the responsibilities',
                    'What is the main takeaway?',
                ];
            }
            if ($intent->hasConstraint('only_responsibilities')) {
                return [
                    'Extract all team members',
                    'Compare the dates and amounts',
                    'Show differences in a table',
                ];
            }
            if ($intent->hasConstraint('format_markdown_table')) {
                return [
                    'Highlight the biggest difference',
                    'Make it shorter',
                    'Compare responsibilities',
                ];
            }
            return [
                'Compare these documents in a table',
                'Compare only the responsibilities',
                'Tell me what is different in simple terms',
            ];
        }

        return match ($intent->intent) {
            'numerical_comparison' => [
                'Show all payment timeline changes',
                'Compare the dates and amounts',
                'Show differences in a table',
            ],
            'role_and_task_extraction' => [
                'Who has product vision and tech responsibilities?',
                'What skills are team members learning?',
                'List all marketing and operations duties',
            ],
            'section_specific' => [
                'What does the preceding section cover?',
                'Are there related clauses in other sections?',
                'Summarize the complete document overview',
            ],
            default => [
                'Summarize the key points',
                'Show the differences in a table',
                'What are the main takeaways?',
            ],
        };
    }
}
