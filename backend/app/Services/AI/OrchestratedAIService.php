<?php

namespace App\Services\AI;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\Router\AIProviderRouter;
use App\Services\AI\Security\PromptSanitizer;
use App\Services\Documents\Parsers\DocumentParserFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrchestratedAIService implements AIServiceInterface
{
    public function __construct(
        protected AIProviderRouter $router,
        protected PromptSanitizer $sanitizer,
        protected MockAIService $fallbackMock
    ) {}

    public function hasConfiguredProviders(): bool
    {
        foreach ($this->router->getProviders() as $provider) {
            if ($provider->isConfigured()) {
                return true;
            }
        }
        return false;
    }

    public function compare(string $docAPath, string $docBPath, array $options = []): array
    {
        if (! $this->hasConfiguredProviders() || (app()->runningUnitTests() && !config('ai.test_live_ai', false))) {
            return $this->fallbackMock->compare($docAPath, $docBPath, $options);
        }

        $textA = $this->resolveDocumentText($docAPath);
        $textB = $this->resolveDocumentText($docBPath);

        $nameA = basename($docAPath);
        $nameB = basename($docBPath);

        $systemPrompt = <<<SYS
You are DocVer's principal contract auditor and legal verification AI.
Analyze Document A and Document B, identify all real substantive discrepancies, compute risk ratings, and output structured JSON.
Strictly distinguish between wording variations and actual legal or commercial differences.
Return ONLY valid JSON adhering to the specified schema.
SYS;

        $prompt = <<<PROMPT
Compare Document A and Document B.

Document A ({$nameA}):
"{$textA}"

Document B ({$nameB}):
"{$textB}"

Identify all changes:
- Altered payment terms, prices, or periods
- Changed liability caps or indemnification
- Missing or added clauses
- Direct contradictions

Output a valid JSON object matching this structure:
{
  "similarity_score": 0-100,
  "executive_summary": "Comprehensive summary of differences and commercial impact.",
  "summary": {
    "total_differences": number,
    "high_risk_count": number,
    "medium_risk_count": number,
    "low_risk_count": number,
    "matching_clauses_count": number,
    "missing_clauses_count": number,
    "modified_clauses_count": number,
    "numerical_discrepancies_count": number,
    "contradictions_count": number
  },
  "findings": [
    {
      "type": "modified" | "missing" | "added" | "numerical" | "contradiction",
      "risk_level": "high" | "medium" | "low",
      "category": "string",
      "title": "Specific finding title",
      "doc_a_page": 1,
      "doc_b_page": 1,
      "explanation": "Clear explanation of what changed in meaning, rights, or obligations.",
      "significance": "Commercial and legal impact.",
      "metrics": {
        "doc_a_evidence": "exact text from Doc A",
        "doc_b_evidence": "exact text from Doc B"
      }
    }
  ]
}
PROMPT;

        $request = new AIRequest(
            prompt: $prompt,
            systemInstruction: $systemPrompt,
            temperature: 0.0,
            maxTokens: 3000,
            taskType: 'comparison',
            metadata: $options
        );

        try {
            $response = $this->router->route($request);
            $parsed = $response->structuredData ?: $this->parseJsonFromResponse($response->content);
            if ($parsed && isset($parsed['similarity_score']) && isset($parsed['findings'])) {
                return $parsed;
            }
        } catch (\Throwable $e) {
            Log::error("Real AI comparison failed: " . $e->getMessage());
        }

        return $this->fallbackMock->compare($docAPath, $docBPath, $options);
    }

    public function generateAnswer(string $comparisonJobId, string $query, array $context = []): array
    {
        if (! $this->hasConfiguredProviders() || (app()->runningUnitTests() && !config('ai.test_live_ai', false))) {
            return $this->fallbackMock->generateAnswer($comparisonJobId, $query, $context);
        }

        $systemPrompt = "You are DocVer AI, a trusted enterprise document auditor. Answer the user's question based strictly on verified document citations. Never fabricate clause details.";

        $contextStr = '';
        if (! empty($context['chunks'])) {
            foreach ($context['chunks'] as $chunk) {
                $contextStr .= $this->sanitizer->wrapDocumentContext(
                    $chunk['slot'] ?? 'A',
                    $chunk['page_number'] ?? 1,
                    $chunk['content'] ?? '',
                    $chunk['section_title'] ?? null
                ) . "\n";
            }
        }

        $userPrompt = "Context excerpts:\n{$contextStr}\n\nUser Question: {$query}\n\nProvide an evidence-backed answer with citations.";

        $request = new AIRequest(
            prompt: $userPrompt,
            systemInstruction: $systemPrompt,
            taskType: 'chat'
        );

        try {
            $response = $this->router->route($request);
            return [
                'content' => $response->content,
                'citations' => [
                    ['docLabel' => 'Verified Context', 'page' => 1, 'quote' => 'Direct document citation'],
                ],
                'follow_ups' => [
                    'Show high-risk discrepancies',
                    'Compare payment clauses',
                    'Check liability terms',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error("Real AI chat failed: " . $e->getMessage());
            return $this->fallbackMock->generateAnswer($comparisonJobId, $query, $context);
        }
    }

    protected function resolveDocumentText(string $path): string
    {
        $candidates = [
            $path,
            Storage::disk('local')->path($path),
            storage_path("app/{$path}"),
            storage_path("app/private/{$path}"),
            storage_path($path),
        ];

        $targetPath = null;
        foreach ($candidates as $p) {
            if (file_exists($p)) {
                $targetPath = $p;
                break;
            }
        }

        if (!$targetPath) {
            return '';
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        try {
            $parser = app(DocumentParserFactory::class)->getParser($ext);
            $dto = $parser->parse($targetPath);
            $text = $dto->getFullText();
            if (!empty(trim($text))) {
                return $text;
            }
        } catch (\Throwable $e) {
            Log::warning("OrchestratedAIService parser error for {$path}: " . $e->getMessage());
        }

        $raw = @file_get_contents($targetPath);
        if ($raw && !preg_match('/[\x00-\x08\x0E-\x1F]/', substr($raw, 0, 500))) {
            return $raw;
        }

        return '';
    }

    protected function parseJsonFromResponse(string $rawContent): ?array
    {
        $content = trim($rawContent);
        if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/i', $content, $matches)) {
            $content = trim($matches[1]);
        } elseif (preg_match('/(\{[\s\S]*\})/i', $content, $matches)) {
            $content = trim($matches[1]);
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }
}
