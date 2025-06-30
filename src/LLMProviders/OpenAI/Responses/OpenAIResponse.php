<?php

namespace LaraUtilX\LLMProviders\OpenAI\Responses;

use JsonSerializable;

class OpenAIResponse implements JsonSerializable
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $model = null,
        public readonly ?object $usage = null,
        public readonly ?object $rawResponse = null
    ) {}

    /**
     * Get the response content
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Get the model used for the response
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get the token usage information
     */
    public function getUsage(): ?object
    {
        return $this->usage;
    }

    /**
     * Get the raw response from the API
     */
    public function getRawResponse(): ?object
    {
        return $this->rawResponse;
    }

    /**
     * Convert the response to an array
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'usage' => $this->usage,
        ];
    }

    /**
     * Convert the response to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
} 