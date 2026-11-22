<?php

namespace LaraUtilX\Tests\Unit\LLMProviders;

use LaraUtilX\LLMProviders\OpenAI\OpenAIProvider;
use LaraUtilX\Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    /**
     * openai-php/client is optional and needs PHP 8.2+, so it is not a dev
     * dependency. The request building is plain logic and is exercised here
     * without the client.
     */
    private function buildParameters(array $overrides = []): array
    {
        $class = new \ReflectionClass(OpenAIProvider::class);
        $provider = $class->newInstanceWithoutConstructor();

        $method = $class->getMethod('buildParameters');
        $method->setAccessible(true);

        return $method->invokeArgs($provider, array_merge([
            'modelName' => 'gpt-5.6-terra',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'temperature' => null,
            'maxTokens' => null,
            'stop' => null,
            'topP' => null,
            'frequencyPenalty' => null,
            'presencePenalty' => null,
            'logitBias' => null,
            'user' => null,
            'jsonMode' => false,
        ], $overrides));
    }

    public function test_token_limit_is_sent_as_max_completion_tokens()
    {
        $parameters = $this->buildParameters(['maxTokens' => 100]);

        // Reasoning models, including the GPT-5 family, reject max_tokens.
        $this->assertEquals(100, $parameters['max_completion_tokens']);
        $this->assertArrayNotHasKey('max_tokens', $parameters);
    }

    public function test_unset_parameters_are_omitted()
    {
        $parameters = $this->buildParameters();

        $this->assertEquals(['model', 'messages'], array_keys($parameters));
    }

    public function test_json_mode_sets_the_response_format()
    {
        $parameters = $this->buildParameters(['jsonMode' => true]);

        $this->assertEquals(['type' => 'json_object'], $parameters['response_format']);
    }

    public function test_missing_client_library_gives_an_actionable_error()
    {
        if (class_exists(\OpenAI::class)) {
            $this->markTestSkipped('openai-php/client is installed.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('composer require openai-php/client');

        new OpenAIProvider(apiKey: 'test');
    }

    public function test_default_model_is_not_a_retired_one()
    {
        $this->assertNotEquals('gpt-3.5-turbo', config('lara-util-x.openai.default_model'));
    }
}
