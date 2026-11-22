<?php

namespace LaraUtilX\LLMProviders\Gemini;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaraUtilX\LLMProviders\Contracts\LLMProviderInterface;
use LaraUtilX\LLMProviders\Exceptions\LLMRequestException;
use LaraUtilX\LLMProviders\Gemini\Responses\GeminiResponse;

class GeminiProvider implements LLMProviderInterface
{
    private string $apiKey;
    private int $maxRetries;
    private int $retryDelay;
    private string $baseUrl;

    public function __construct(
        string $apiKey,
        int $maxRetries = 3,
        int $retryDelay = 2,
        string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta'
    ) {
        $this->apiKey = $apiKey;
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function generateResponse(
        string $modelName,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        ?float $topP = null,
        ?float $frequencyPenalty = null,
        ?float $presencePenalty = null,
        ?array $logitBias = null,
        ?string $user = null,
        ?bool $jsonMode = false,
        bool $fullResponse = false
    ): GeminiResponse {
        // The key travels in a header rather than the query string, where it
        // would end up in proxy logs and in any exception that echoes the URL.
        $endpoint = $this->baseUrl . '/models/' . $modelName . ':generateContent';

        $payload = $this->buildPayload($messages, $temperature, $maxTokens, $stop, $topP, $jsonMode === true);

        return $this->executeWithRetry(function () use ($endpoint, $payload, $fullResponse, $modelName) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $this->apiKey,
            ])->post($endpoint, $payload);

            if (!$response->successful()) {
                throw LLMRequestException::fromResponse(
                    'Gemini',
                    $response->status(),
                    $response->json('error.message'),
                    $modelName,
                    'GEMINI_DEFAULT_MODEL'
                );
            }

            $data = $response->json();

            $text = '';
            if (isset($data['candidates'][0]['content']['parts'])) {
                foreach ($data['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $text .= $part['text'];
                    }
                }
            }

            return new GeminiResponse(
                content: $text,
                model: $data['modelVersion'] ?? $data['model'] ?? null,
                usage: (object) ($data['usageMetadata'] ?? []),
                rawResponse: $fullResponse ? (object) $data : null
            );
        });
    }

    private function buildPayload(
        array $messages,
        ?float $temperature,
        ?int $maxTokens,
        ?array $stop,
        ?float $topP,
        bool $jsonMode
    ): array {
        [$system, $contents] = $this->mapMessagesToGeminiContents($messages);

        $payload = ['contents' => $contents];

        if ($system !== null) {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $generationConfig = array_filter([
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens,
            'topP' => $topP,
            'stopSequences' => $stop,
        ], fn ($value) => $value !== null);

        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        return $payload;
    }

    /**
     * Gemini only accepts 'user' and 'model' as content roles and takes the
     * system prompt as a top-level systemInstruction, so OpenAI-style system
     * messages are lifted out and assistant turns are renamed.
     *
     * @return array{0: string|null, 1: array}
     */
    private function mapMessagesToGeminiContents(array $messages): array
    {
        $system = [];
        $contents = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $text = $message['content'] ?? '';

            if ($role === 'system') {
                $system[] = $text;
                continue;
            }

            $contents[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $text]],
            ];
        }

        return [
            $system === [] ? null : implode("\n\n", array_filter($system)),
            $contents,
        ];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function executeWithRetry(callable $callback)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;

                if ($e instanceof LLMRequestException && ! $e->isRetryable()) {
                    break;
                }

                if ($attempt < $this->maxRetries) {
                    Log::warning("Gemini API request failed, retrying...", [
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    sleep($this->retryDelay);
                }
            }
        }

        Log::error("Gemini API request failed after {$attempt} attempt(s)", [
            'error' => $lastException?->getMessage()
        ]);

        throw $lastException ?? new \RuntimeException('Unknown Gemini error');
    }
}


