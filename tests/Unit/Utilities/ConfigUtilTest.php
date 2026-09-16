<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use Illuminate\Support\Facades\Storage;
use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\ConfigUtil;

class ConfigUtilTest extends TestCase
{
    private ConfigUtil $configUtil;

    protected function setUp(): void
    {
        parent::setUp();

        // A fake disk, so settings are genuinely written and read back rather
        // than asserted through a mocked Storage::put.
        Storage::fake();

        $this->configUtil = new ConfigUtil();
    }

    // -----------------------------------------------------------------------
    // App settings
    // -----------------------------------------------------------------------

    public function test_can_get_all_app_settings()
    {
        $settings = $this->configUtil->getAllAppSettings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('name', $settings);
        $this->assertArrayHasKey('env', $settings);
    }

    // -----------------------------------------------------------------------
    // Round trips
    // -----------------------------------------------------------------------

    public function test_setting_is_persisted_and_read_back()
    {
        $this->configUtil->setSetting('site_name', 'LaraUtilX');

        $this->assertEquals('LaraUtilX', $this->configUtil->getSetting('site_name'));
    }

    public function test_setting_survives_a_new_instance()
    {
        $this->configUtil->setSetting('theme', 'dark');

        $this->assertEquals('dark', (new ConfigUtil())->getSetting('theme'));
    }

    public function test_can_update_an_existing_setting()
    {
        $this->configUtil->setSetting('mode', 'initial');
        $this->configUtil->setSetting('mode', 'updated');

        $this->assertEquals('updated', $this->configUtil->getSetting('mode'));
    }

    public function test_setting_a_key_leaves_other_keys_intact()
    {
        $this->configUtil->setSetting('first', 'one');
        $this->configUtil->setSetting('second', 'two');

        $this->assertEquals('one', $this->configUtil->getSetting('first'));
        $this->assertEquals('two', $this->configUtil->getSetting('second'));
    }

    public function test_supports_dot_notation()
    {
        $this->configUtil->setSetting('mail.from', 'hello@example.com');

        $this->assertEquals('hello@example.com', $this->configUtil->getSetting('mail.from'));
        $this->assertEquals(['from' => 'hello@example.com'], $this->configUtil->getSetting('mail'));
    }

    public function test_can_forget_a_setting()
    {
        $this->configUtil->setSetting('temporary', 'value');
        $this->configUtil->forgetSetting('temporary');

        $this->assertNull($this->configUtil->getSetting('temporary'));
    }

    public function test_settings_file_lands_at_the_configured_path()
    {
        $this->configUtil->setSetting('anything', 'value');

        Storage::assertExists('config/settings.json');
    }

    public function test_non_ascii_values_survive_the_round_trip()
    {
        $this->configUtil->setSetting('greeting', 'مرحبا بيروت');

        $this->assertEquals('مرحبا بيروت', $this->configUtil->getSetting('greeting'));
    }

    // -----------------------------------------------------------------------
    // Defaults and missing data
    // -----------------------------------------------------------------------

    public function test_returns_null_for_non_existent_setting()
    {
        $this->assertNull($this->configUtil->getSetting('non_existent_key'));
    }

    public function test_returns_given_default_for_non_existent_setting()
    {
        $this->assertEquals('fallback', $this->configUtil->getSetting('missing', 'fallback'));
    }

    public function test_returns_empty_array_for_non_existent_file()
    {
        $this->assertEquals([], $this->configUtil->getAllSettings('non_existent_file.json'));
    }

    public function test_can_read_settings_from_an_explicit_path()
    {
        $expected = ['test_key' => 'test_value'];
        Storage::put('test_settings.json', json_encode($expected));

        $this->assertEquals($expected, $this->configUtil->getAllSettings('test_settings.json'));
    }
}
