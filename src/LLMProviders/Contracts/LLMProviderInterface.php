<?php

namespace omarchouman\LaraUtilX\LLMProviders\Contracts;

interface LLMProviderInterface
{
    /**
     * Generate a response from the LLM
     *
     * @param string $modelName The model to use
     * @param array $messages The messages to send to the model
     * @param float|null $temperature Controls randomness: 0 is deterministic, 1 is creative
     * @param int|null $maxTokens Maximum number of tokens to generate
     * @param array|null $stop Sequences where the API will stop generating
     * @param float|null $topP Controls diversity via nucleus sampling
     * @param float|null $frequencyPenalty Reduces repetition of token sequences
     * @param float|null $presencePenalty Encourages model to talk about new topics
     * @param array|null $logitBias Modifies likelihood of specified tokens
     * @param string|null $user A unique identifier for the end-user
     * @param bool|null $jsonMode Whether to force JSON output
     * @param bool $fullResponse Whether to return the full response object
     * @return mixed
     */
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
    );
} 