<?php

namespace LaraUtilX\LLMProviders\Exceptions;

use RuntimeException;

class LLMRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $status = null,
        public readonly string $provider = ''
    ) {
        parent::__construct($message, $status ?? 0);
    }

    /**
     * Build an exception from a failed HTTP response. A missing model gets a
     * message naming the env var to change, because vendors retire models and
     * the bare API error does not say where the model name came from.
     */
    public static function fromResponse(string $provider, int $status, ?string $apiMessage, string $modelName, string $modelEnv): self
    {
        $message = $apiMessage ?: "{$provider} API request failed with status {$status}.";

        if ($status === 404) {
            $message = "{$provider} model [{$modelName}] was not found. It may have been retired; "
                . "set {$modelEnv} to a current model. API said: {$message}";
        }

        return new self($message, $status, $provider);
    }

    /**
     * Rate limits, timeouts and server errors can succeed on a later attempt.
     * Other client errors, such as a bad key or an unknown model, cannot, so
     * retrying them only delays the error.
     */
    public function isRetryable(): bool
    {
        if ($this->status === null) {
            return true;
        }

        return $this->status === 408 || $this->status === 429 || $this->status >= 500;
    }
}
