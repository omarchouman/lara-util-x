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

    // -----------------------------------------------------------------------
    // Coverage of credential field names (1.5.5)
    // -----------------------------------------------------------------------

    public function test_current_password_is_redacted()
    {
        // Breeze's password-update form posts current_password.
        $this->handle(Request::create('/user/password', 'PUT', [
            'current_password' => 'the-old-one',
            'password' => 'the-new-one',
            'password_confirmation' => 'the-new-one',
        ]));

        // Every field on the form is a credential, so nothing survives
        // redaction and only the request metadata is recorded.
        $log = AccessLog::first();

        $this->assertNull($log->request_data);
        $this->assertEquals('PUT', $log->method);
    }

    public function test_additional_credential_fields_are_redacted()
    {
        $this->handle(Request::create('/x', 'POST', [
            'new_password' => 'a',
            'api_key' => 'b',
            'client_secret' => 'c',
            'keep' => 'd',
        ]));

        $data = json_decode(AccessLog::first()->request_data, true);

        foreach (['new_password', 'api_key', 'client_secret'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }

        $this->assertEquals('d', $data['keep']);
    }

    // -----------------------------------------------------------------------
    // Nesting and casing
    // -----------------------------------------------------------------------

    public function test_nested_credentials_are_redacted()
    {
        // except() only strips top-level keys, so this used to survive.
        $this->handle(Request::create('/register', 'POST', [
            'user' => ['email' => 'user@example.com', 'password' => 'hunter2'],
        ]));

        $data = json_decode(AccessLog::first()->request_data, true);

        $this->assertArrayNotHasKey('password', $data['user']);
        $this->assertEquals('user@example.com', $data['user']['email']);
    }

    public function test_deeply_nested_credentials_are_redacted()
    {
        $this->handle(Request::create('/x', 'POST', [
            'a' => ['b' => ['c' => ['api_key' => 'leaked', 'safe' => 'kept']]],
        ]));

        $data = json_decode(AccessLog::first()->request_data, true);

        $this->assertArrayNotHasKey('api_key', $data['a']['b']['c']);
        $this->assertEquals('kept', $data['a']['b']['c']['safe']);
    }

    public function test_body_key_casing_is_ignored()
    {
        $this->handle(Request::create('/x', 'POST', [
            'Password' => 'one',
            'API_KEY' => 'two',
        ]));

        $this->assertNull(AccessLog::first()->request_data);
    }

    public function test_query_string_matching_is_case_insensitive()
    {
        $this->handle(Request::create('/auth?Token=leaked&API_KEY=alsoleaked&page=2', 'GET'));

        $url = AccessLog::first()->url;

        $this->assertStringNotContainsString('leaked', $url);
        $this->assertStringNotContainsString('alsoleaked', $url);
        $this->assertStringContainsString('page=2', $url);
    }

    public function test_dotted_exclusions_target_one_nested_key()
    {
        Config::set('lara-util-x.access_log.excluded_attributes', ['user.pin']);

        $this->handle(Request::create('/x', 'POST', [
            'user' => ['pin' => 'hidden', 'name' => 'kept'],
            'pin' => 'top-level-kept',
        ]));

        $data = json_decode(AccessLog::first()->request_data, true);

        $this->assertArrayNotHasKey('pin', $data['user']);
        $this->assertEquals('kept', $data['user']['name']);
        $this->assertEquals('top-level-kept', $data['pin']);
    }
}
