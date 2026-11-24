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

class OpenRouterProvider extends BaseAIProvider
{
    protected ?string $apiKey;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('ai.providers.openrouter.api_key');
        $this->baseUrl = config('ai.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');
        $this->timeout = config('ai.providers.openrouter.timeout_seconds', 60);
    }

    public function getName(): string
    {
        return 'openrouter';
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
            throw new AIProviderException("OpenRouter API key is not configured in environment.", $this->getName(), 401);
        }

        $model = $request->model ?? $this->getDefaultModel($request->taskType);
        $url = "{$this->baseUrl}/chat/completions";

        $messages = [];
        if ($request->systemInstruction) {
            $messages[] = [
                'role' => 'system',
                'content' => $request->systemInstruction,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $request->prompt,
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $request->temperature,
        ];

        if ($request->maxTokens) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if ($request->wantsJson()) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $start = microtime(true);

        $response = $this->executeWithBackoff(function () use ($url, $payload) {
            $res = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'HTTP-Referer' => 'https://docver.io',
                    'X-Title' => 'DocVer Verification SaaS',
                    'Content-Type' => 'application/json',
                ])
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

        $content = $data['choices'][0]['message']['content'] ?? '';
        $usageData = $data['usage'] ?? [];

        $promptTokens = $usageData['prompt_tokens'] ?? (int) ceil(strlen($request->prompt) / 4);
        $completionTokens = $usageData['completion_tokens'] ?? (int) ceil(strlen($content) / 4);
        $totalTokens = $usageData['total_tokens'] ?? ($promptTokens + $completionTokens);

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
                    "Failed to decode OpenRouter JSON response: " . json_last_error_msg(),
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
            throw new AIProviderException("OpenRouter API key is not configured in environment.", $this->getName(), 401);
        }

        $model = $request->model ?? $this->getDefaultModel($request->taskType);
        $url = "{$this->baseUrl}/chat/completions";

        $messages = [];
        if ($request->systemInstruction) {
            $messages[] = ['role' => 'system', 'content' => $request->systemInstruction];
        }
        $messages[] = ['role' => 'user', 'content' => $request->prompt];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $request->temperature,
            'stream' => true,
        ];

        $streamResponse = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'HTTP-Referer' => 'https://docver.io',
                'X-Title' => 'DocVer Verification SaaS',
                'Content-Type' => 'application/json',
            ])
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
                    if ($jsonStr === '[DONE]') {
                        yield new StreamChunkDTO(delta: '', isDone: true);
                        return;
                    }

                    $decoded = json_decode($jsonStr, true);
                    $text = $decoded['choices'][0]['delta']['content'] ?? '';
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

        $res = Http::timeout(5)
            ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
            ->get("{$this->baseUrl}/models");

        return $res->successful();
    }
}
