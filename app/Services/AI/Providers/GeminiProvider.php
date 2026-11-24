<?php

namespace App\Services\AI\Providers;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\StreamChunkDTO;
use App\Services\AI\DTOs\UsageStatsDTO;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\InvalidStructuredOutputException;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider extends BaseAIProvider
{
    protected ?string $apiKey;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.gemini.api_key');
        $this->baseUrl = config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->timeout = config('ai.providers.gemini.timeout_seconds', 60);
    }

    public function getName(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    public function supportsStructuredOutput(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function generate(AIRequest $request): AIResponse
    {
        if (! $this->isConfigured()) {
            throw new AIProviderException("Gemini API key is not configured in environment.", $this->getName(), 401);
        }

        $model = $request->model ?? $this->getDefaultModel($request->taskType);
        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $request->prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $request->temperature,
            ],
        ];

        if ($request->maxTokens) {
            $payload['generationConfig']['maxOutputTokens'] = $request->maxTokens;
        }

        if ($request->systemInstruction) {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $request->systemInstruction],
                ],
            ];
        }

        if ($request->wantsJson()) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
            if (! empty($request->jsonSchema)) {
                $payload['generationConfig']['responseSchema'] = $request->jsonSchema;
            }
        }

        $start = microtime(true);

        $response = $this->executeWithBackoff(function () use ($url, $payload) {
            $res = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if (! $res->successful()) {
                $this->handleResponseError($res);
            }

            return $res;
        });

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $data = $response->json();
        $headers = $response->headers();
        $quotaHeaders = $this->extractQuotaHeaders($headers);

        $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $usageMeta = $data['usageMetadata'] ?? [];

        $promptTokens = $usageMeta['promptTokenCount'] ?? (int) ceil(strlen($request->prompt) / 4);
        $completionTokens = $usageMeta['candidatesTokenCount'] ?? (int) ceil(strlen($content) / 4);
        $totalTokens = $usageMeta['totalTokenCount'] ?? ($promptTokens + $completionTokens);

        $cost = $this->calculateCost($model, $promptTokens, $completionTokens);

        $usage = new UsageStatsDTO(
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $totalTokens,
            estimatedCostUsd: $cost,
            durationMs: $durationMs
        );

        $structured = null;
        if ($request->wantsJson()) {
            $structured = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidStructuredOutputException(
                    $this->getName(),
                    "Failed to decode Gemini JSON response: " . json_last_error_msg(),
                    $content
                );
            }
        }

        if (is_array($data)) {
            $data['quota_headers'] = $quotaHeaders;
        }

        return new AIResponse(
            content: $content,
            structuredData: $structured,
            provider: $this->getName(),
            model: $model,
            usage: $usage,
            isFallback: false,
            raw: $data
        );
    }

    public function stream(AIRequest $request): Generator
    {
        if (! $this->isConfigured()) {
            throw new AIProviderException("Gemini API key is not configured in environment.", $this->getName(), 401);
        }

        $model = $request->model ?? $this->getDefaultModel($request->taskType);
        $url = "{$this->baseUrl}/models/{$model}:streamGenerateContent?alt=sse&key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $request->prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $request->temperature,
            ],
        ];

        if ($request->systemInstruction) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $request->systemInstruction]],
            ];
        }

        $streamResponse = Http::timeout($this->timeout)
            ->withOptions(['stream' => true])
            ->post($url, $payload);

        $body = $streamResponse->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(1024);
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                if (str_starts_with($event, "data: ")) {
                    $jsonStr = trim(substr($event, 6));
                    $decoded = json_decode($jsonStr, true);
                    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    if (! empty($text)) {
                        yield new StreamChunkDTO(delta: $text, isDone: false);
                    }
                }
            }
        }

        yield new StreamChunkDTO(delta: '', isDone: true);
    }

    public function ping(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $url = "{$this->baseUrl}/models?key={$this->apiKey}";
        $res = Http::timeout(5)->get($url);

        return $res->successful();
    }
}
