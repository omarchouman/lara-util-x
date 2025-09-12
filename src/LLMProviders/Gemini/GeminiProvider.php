<?php

namespace LaraUtilX\LLMProviders\Gemini;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaraUtilX\LLMProviders\Contracts\LLMProviderInterface;
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
        $endpoint = $this->baseUrl . '/models/' . $modelName . ':generateContent?key=' . urlencode($this->apiKey);

        $contents = $this->mapMessagesToGeminiContents($messages, $jsonMode === true);

        $generationConfig = [];
        if ($temperature !== null) {
            $generationConfig['temperature'] = $temperature;
        }
        if ($maxTokens !== null) {
            $generationConfig['maxOutputTokens'] = $maxTokens;
        }
        if ($topP !== null) {
            $generationConfig['topP'] = $topP;
        }
        if ($stop !== null) {
            $generationConfig['stopSequences'] = $stop;
        }

        $payload = [
            'contents' => $contents,
        ];
        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        return $this->executeWithRetry(function () use ($endpoint, $payload, $fullResponse, $jsonMode) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($endpoint, $payload);

            if (!$response->successful()) {
                $body = $response->json();
                $message = $body['error']['message'] ?? 'Gemini API request failed';
                throw new \RuntimeException($message);
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
                model: $data['model'] ?? null,
                usage: (object) ($data['usageMetadata'] ?? []),
                rawResponse: $fullResponse ? (object) $data : null
            );
        });
    }

    private function mapMessagesToGeminiContents(array $messages, bool $jsonMode): array
    {
        $contents = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $text = $message['content'] ?? '';

            // Gemini uses 'user' and 'model' roles
            if ($role === 'assistant') {
                $role = 'model';
            }

            $parts = [
                ['text' => $text]
            ];

            if ($jsonMode === true) {
                $parts = [
                    [
                        'text' => $text
                    ]
                ];
            }

            $contents[] = [
                'role' => $role,
                'parts' => $parts
            ];
        }

        return $contents;
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

                if ($attempt < $this->maxRetries) {
                    Log::warning("Gemini API request failed, retrying...", [
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    sleep($this->retryDelay);
                }
            }
        }

        Log::error("Gemini API request failed after {$this->maxRetries} attempts", [
            'error' => $lastException?->getMessage()
        ]);

        throw $lastException ?? new \RuntimeException('Unknown Gemini error');
    }
}


