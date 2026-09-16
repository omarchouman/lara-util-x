<?php

namespace LaraUtilX\Tests\Feature\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use LaraUtilX\Http\Middleware\AccessLogMiddleware;
use LaraUtilX\Models\AccessLog;
use LaraUtilX\Tests\TestCase;

class AccessLogMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }

    private function handle(Request $request): void
    {
        (new AccessLogMiddleware())->handle($request, fn ($r) => response('ok'));
    }

    // -----------------------------------------------------------------------
    // Redaction
    // -----------------------------------------------------------------------

    public function test_passwords_are_not_written_to_the_access_log()
    {
        $this->handle(Request::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'hunter2',
        ]));

        $data = json_decode(AccessLog::first()->request_data, true);

        $this->assertArrayNotHasKey('password', $data);
        $this->assertEquals('user@example.com', $data['email']);
    }

    public function test_tokens_are_not_written_to_the_access_log()
    {
        $this->handle(Request::create('/callback', 'POST', [
            'api_token' => 'secret-token',
            'remember_token' => 'another',
            'state' => 'keep-me',
        ]));

        $data = json_decode(AccessLog::first()->request_data, true);

        $this->assertArrayNotHasKey('api_token', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
        $this->assertEquals('keep-me', $data['state']);
    }

    public function test_credentials_in_the_query_string_are_redacted()
    {
        $this->handle(Request::create('/auth?token=leaked&page=2', 'GET'));

        $url = AccessLog::first()->url;

        $this->assertStringNotContainsString('leaked', $url);
        $this->assertStringContainsString('redacted', $url);
        $this->assertStringContainsString('page=2', $url);
    }

    public function test_url_without_a_query_string_is_left_alone()
    {
        $this->handle(Request::create('/plain', 'GET'));

        $this->assertStringEndsWith('/plain', AccessLog::first()->url);
    }

    public function test_exclusion_list_is_configurable()
    {
        Config::set('lara-util-x.access_log.excluded_attributes', ['nickname']);

        $this->handle(Request::create('/x', 'POST', [
            'nickname' => 'hidden',
            'password' => 'not-excluded-now',
        ]));

        $data = json_decode(AccessLog::first()->request_data, true);

        $this->assertArrayNotHasKey('nickname', $data);
        $this->assertArrayHasKey('password', $data);
    }

    // -----------------------------------------------------------------------
    // Recording
    // -----------------------------------------------------------------------

    public function test_request_metadata_is_recorded()
    {
        $this->handle(Request::create('/things', 'POST', ['a' => 'b']));

        $log = AccessLog::first();

        $this->assertEquals('POST', $log->method);
        $this->assertNotNull($log->url);
    }

    public function test_empty_body_records_null_request_data()
    {
        $this->handle(Request::create('/things', 'GET'));

        $this->assertNull(AccessLog::first()->request_data);
    }

    // -----------------------------------------------------------------------
    // Pruning
    // -----------------------------------------------------------------------

    public function test_old_rows_are_prunable()
    {
        Config::set('lara-util-x.access_log.retention_days', 30);

        AccessLog::create(['ip' => '127.0.0.1', 'method' => 'GET', 'url' => '/old'])
            ->forceFill(['created_at' => now()->subDays(60)])->save();
        AccessLog::create(['ip' => '127.0.0.1', 'method' => 'GET', 'url' => '/new']);

        $this->assertEquals(1, (new AccessLog())->prunable()->count());
    }

    public function test_null_retention_keeps_everything()
    {
        Config::set('lara-util-x.access_log.retention_days', null);

        AccessLog::create(['ip' => '127.0.0.1', 'method' => 'GET', 'url' => '/old'])
            ->forceFill(['created_at' => now()->subYears(5)])->save();

        $this->assertEquals(0, (new AccessLog())->prunable()->count());
    }
}
