<?php

namespace LaraUtilX\Tests\Unit\LLMProviders;

use Illuminate\Support\Facades\Http;
use LaraUtilX\LLMProviders\Claude\ClaudeProvider;
use LaraUtilX\Tests\TestCase;

class ClaudeProviderTest extends TestCase
{
    private function fakeClaude(): void
    {
        Http::fake([
            '*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'hello']],
                'model' => 'claude-sonnet-5',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ], 200),
        ]);
    }

    private function provider(): ClaudeProvider
    {
        return new ClaudeProvider(apiKey: 'test-key', maxRetries: 1, retryDelay: 0);
    }

    private function sentPayload(): array
    {
        $payload = [];

        Http::assertSent(function ($request) use (&$payload) {
            $payload = $request->data();

            return true;
        });

        return $payload;
    }

    // -----------------------------------------------------------------------
    // System prompt handling
    // -----------------------------------------------------------------------

    public function test_system_message_is_lifted_to_a_top_level_parameter()
    {
        $this->fakeClaude();

        $this->provider()->generateResponse('claude-sonnet-5', [
            ['role' => 'system', 'content' => 'You are terse.'],
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $payload = $this->sentPayload();

        // Anthropic rejects a system role inside messages.
        $this->assertEquals('You are terse.', $payload['system']);
        $this->assertCount(1, $payload['messages']);
        $this->assertEquals('user', $payload['messages'][0]['role']);
    }

    public function test_multiple_system_messages_are_joined()
    {
        $this->fakeClaude();

        $this->provider()->generateResponse('claude-sonnet-5', [
            ['role' => 'system', 'content' => 'First.'],
            ['role' => 'system', 'content' => 'Second.'],
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertEquals("First.\n\nSecond.", $this->sentPayload()['system']);
    }

    public function test_no_system_key_when_there_is_no_system_message()
    {
        $this->fakeClaude();

        $this->provider()->generateResponse('claude-sonnet-5', [
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertArrayNotHasKey('system', $this->sentPayload());
    }

    public function test_conversation_messages_keep_their_order()
    {
        $this->fakeClaude();

        $this->provider()->generateResponse('claude-sonnet-5', [
            ['role' => 'user', 'content' => 'one'],
            ['role' => 'assistant', 'content' => 'two'],
            ['role' => 'system', 'content' => 'ignore me'],
            ['role' => 'user', 'content' => 'three'],
        ]);

        $messages = $this->sentPayload()['messages'];

        $this->assertEquals(['one', 'two', 'three'], array_column($messages, 'content'));
    }

    // -----------------------------------------------------------------------
    // Config
    // -----------------------------------------------------------------------

    public function test_default_model_is_not_a_retired_one()
    {
        $default = config('lara-util-x.claude.default_model');

        $this->assertNotEquals('claude-3-5-sonnet-20241022', $default);
        $this->assertNotEmpty($default);
    }
}
