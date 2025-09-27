<?php

namespace LaraUtilX\LLMProviders\Claude;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaraUtilX\LLMProviders\Contracts\LLMProviderInterface;
use LaraUtilX\LLMProviders\Claude\Responses\ClaudeResponse;

class ClaudeProvider implements LLMProviderInterface
{
    private string $apiKey;
    private int $maxRetries;
    private int $retryDelay;
    private string $baseUrl;

    public function __construct(
        string $apiKey,
        int $maxRetries = 3,
        int $retryDelay = 2,
        string $baseUrl = 'https://api.anthropic.com'
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
    ): ClaudeResponse {
        $endpoint = $this->baseUrl . '/v1/messages';

        $payload = $this->buildPayload(
            modelName: $modelName,
            messages: $messages,
            temperature: $temperature,
            maxTokens: $maxTokens,
            stop: $stop,
            topP: $topP,
            jsonMode: $jsonMode
        );

        return $this->executeWithRetry(function () use ($endpoint, $payload, $fullResponse) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01'
            ])->post($endpoint, $payload);

            if (!$response->successful()) {
                $body = $response->json();
                $message = $body['error']['message'] ?? 'Claude API request failed';
                throw new \RuntimeException($message);
            }

            $data = $response->json();

            $content = '';
            if (isset($data['content'][0]['text'])) {
                $content = $data['content'][0]['text'];
            }

            return new ClaudeResponse(
                content: $content,
                model: $data['model'] ?? null,
                usage: (object) ($data['usage'] ?? []),
                rawResponse: $fullResponse ? (object) $data : null
            );
        });
    }

    private function buildPayload(
        string $modelName,
        array $messages,
        ?float $temperature,
        ?int $maxTokens,
        ?array $stop,
        ?float $topP,
        ?bool $jsonMode
    ): array {
        $payload = [
            'model' => $modelName,
            'messages' => $messages,
            'max_tokens' => $maxTokens ?? 1024,
        ];

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        if ($topP !== null) {
            $payload['top_p'] = $topP;
        }

        if ($stop !== null && !empty($stop)) {
            $payload['stop_sequences'] = $stop;
        }

        // Claude doesn't support frequency_penalty and presence_penalty like OpenAI
        // These parameters are ignored for Claude

        return $payload;
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
                    Log::warning("Claude API request failed, retrying...", [
                        'attempt' => $attempt,
                        'error' => $e->getMessage()
                    ]);
                    sleep($this->retryDelay);
                }
            }
        }

        Log::error("Claude API request failed after {$this->maxRetries} attempts", [
            'error' => $lastException?->getMessage()
        ]);

        throw $lastException ?? new \RuntimeException('Unknown Claude error');
    }
}
