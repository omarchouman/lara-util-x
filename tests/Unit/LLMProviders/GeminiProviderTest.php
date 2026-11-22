<?php

namespace LaraUtilX\Tests\Unit\LLMProviders;

use Illuminate\Support\Facades\Http;
use LaraUtilX\LLMProviders\Exceptions\LLMRequestException;
use LaraUtilX\LLMProviders\Gemini\GeminiProvider;
use LaraUtilX\Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    private function fakeGemini(int $status = 200, ?array $body = null): void
    {
        Http::fake([
            '*' => Http::response($body ?? [
                'candidates' => [['content' => ['parts' => [['text' => 'hel'], ['text' => 'lo']]]]],
                'modelVersion' => 'gemini-3.6-flash',
                'usageMetadata' => ['totalTokenCount' => 3],
            ], $status),
        ]);
    }

    private function provider(int $maxRetries = 1): GeminiProvider
    {
        return new GeminiProvider(apiKey: 'test-key', maxRetries: $maxRetries, retryDelay: 0);
    }

    private function sentRequest(): \Illuminate\Http\Client\Request
    {
        $sent = null;

        Http::assertSent(function ($request) use (&$sent) {
            $sent = $request;

            return true;
        });

        return $sent;
    }

    // -----------------------------------------------------------------------
    // Requests
    // -----------------------------------------------------------------------

    public function test_api_key_is_sent_as_a_header_not_in_the_url()
    {
        $this->fakeGemini();

        $this->provider()->generateResponse('gemini-3.6-flash', [['role' => 'user', 'content' => 'Hi']]);

        $request = $this->sentRequest();

        // A key in the query string ends up in proxy logs and exception messages.
        $this->assertStringNotContainsString('test-key', $request->url());
        $this->assertEquals('test-key', $request->header('x-goog-api-key')[0]);
    }

    public function test_system_message_becomes_a_system_instruction()
    {
        $this->fakeGemini();

        $this->provider()->generateResponse('gemini-3.6-flash', [
            ['role' => 'system', 'content' => 'You are terse.'],
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $payload = $this->sentRequest()->data();

        // Gemini only accepts 'user' and 'model' as content roles.
        $this->assertEquals('You are terse.', $payload['systemInstruction']['parts'][0]['text']);
        $this->assertCount(1, $payload['contents']);
        $this->assertEquals('user', $payload['contents'][0]['role']);
    }

    public function test_no_system_instruction_without_a_system_message()
    {
        $this->fakeGemini();

        $this->provider()->generateResponse('gemini-3.6-flash', [['role' => 'user', 'content' => 'Hi']]);

        $this->assertArrayNotHasKey('systemInstruction', $this->sentRequest()->data());
    }

    public function test_assistant_turns_are_sent_as_model()
    {
        $this->fakeGemini();

        $this->provider()->generateResponse('gemini-3.6-flash', [
            ['role' => 'user', 'content' => 'one'],
            ['role' => 'assistant', 'content' => 'two'],
            ['role' => 'user', 'content' => 'three'],
        ]);

        $roles = array_column($this->sentRequest()->data()['contents'], 'role');

        $this->assertEquals(['user', 'model', 'user'], $roles);
    }

    public function test_json_mode_requests_a_json_response()
    {
        $this->fakeGemini();

        $this->provider()->generateResponse(
            'gemini-3.6-flash',
            [['role' => 'user', 'content' => 'Hi']],
            jsonMode: true
        );

        // jsonMode previously built identical parts and changed nothing.
        $this->assertEquals(
            'application/json',
            $this->sentRequest()->data()['generationConfig']['responseMimeType']
        );
    }

    public function test_generation_parameters_are_mapped()
    {
        $this->fakeGemini();

        $this->provider()->generateResponse(
            'gemini-3.6-flash',
            [['role' => 'user', 'content' => 'Hi']],
            temperature: 0.2,
            maxTokens: 50,
            stop: ['END'],
            topP: 0.9
        );

        $config = $this->sentRequest()->data()['generationConfig'];

        $this->assertEquals(0.2, $config['temperature']);
        $this->assertEquals(50, $config['maxOutputTokens']);
        $this->assertEquals(['END'], $config['stopSequences']);
        $this->assertEquals(0.9, $config['topP']);
    }

    // -----------------------------------------------------------------------
    // Responses
    // -----------------------------------------------------------------------

    public function test_text_parts_are_joined()
    {
        $this->fakeGemini();

        $response = $this->provider()->generateResponse('gemini-3.6-flash', [['role' => 'user', 'content' => 'Hi']]);

        $this->assertEquals('hello', $response->content);
        $this->assertEquals('gemini-3.6-flash', $response->model);
    }

    // -----------------------------------------------------------------------
    // Errors
    // -----------------------------------------------------------------------

    public function test_missing_model_names_the_env_var_to_change()
    {
        $this->fakeGemini(404, ['error' => ['message' => 'models/gemini-2.0-flash is not found']]);

        try {
            $this->provider()->generateResponse('gemini-2.0-flash', [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected an exception');
        } catch (LLMRequestException $e) {
            $this->assertEquals(404, $e->status);
            $this->assertStringContainsString('GEMINI_DEFAULT_MODEL', $e->getMessage());
            $this->assertStringContainsString('gemini-2.0-flash', $e->getMessage());
        }
    }

    public function test_client_errors_are_not_retried()
    {
        $this->fakeGemini(400, ['error' => ['message' => 'bad request']]);

        try {
            $this->provider(maxRetries: 3)->generateResponse('gemini-3.6-flash', [['role' => 'user', 'content' => 'Hi']]);
        } catch (LLMRequestException) {
        }

        Http::assertSentCount(1);
    }

    public function test_server_errors_are_retried()
    {
        $this->fakeGemini(503, ['error' => ['message' => 'overloaded']]);

        try {
            $this->provider(maxRetries: 3)->generateResponse('gemini-3.6-flash', [['role' => 'user', 'content' => 'Hi']]);
        } catch (LLMRequestException) {
        }

        Http::assertSentCount(3);
    }

    public function test_errors_are_still_runtime_exceptions_for_existing_callers()
    {
        $this->fakeGemini(400, ['error' => ['message' => 'bad request']]);

        $this->expectException(\RuntimeException::class);

        $this->provider()->generateResponse('gemini-3.6-flash', [['role' => 'user', 'content' => 'Hi']]);
    }

    // -----------------------------------------------------------------------
    // Config
    // -----------------------------------------------------------------------

    public function test_default_model_is_not_a_retired_one()
    {
        $this->assertNotEquals('gemini-2.0-flash', config('lara-util-x.gemini.default_model'));
    }
}
