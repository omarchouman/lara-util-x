<?php

namespace LaraUtilX\LLMProviders\Gemini\Responses;

use JsonSerializable;

class GeminiResponse implements JsonSerializable
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $model = null,
        public readonly ?object $usage = null,
        public readonly ?object $rawResponse = null
    ) {}

    public function getContent(): string
    {
        return $this->content;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getUsage(): ?object
    {
        return $this->usage;
    }

    public function getRawResponse(): ?object
    {
        return $this->rawResponse;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'model' => $this->model,
            'usage' => $this->usage,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}


