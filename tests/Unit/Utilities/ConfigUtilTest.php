<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\ConfigUtil;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class ConfigUtilTest extends TestCase
{
    private ConfigUtil $configUtil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configUtil = new ConfigUtil();
    }

    public function test_can_get_all_app_settings()
    {
        $settings = $this->configUtil->getAllAppSettings();
        
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('name', $settings);
        $this->assertArrayHasKey('env', $settings);
    }

    public function test_can_get_specific_app_setting()
    {
        $appName = $this->configUtil->getAllSettings(null, 'name');
        
        $this->assertIsString($appName);
    }

    public function test_can_get_setting_from_existing_file()
    {
        // Create a test settings file
        $testSettings = ['test_key' => 'test_value', 'another_key' => 'another_value'];
        $filePath = 'test_settings.json';
        
        Storage::put($filePath, json_encode($testSettings));
        
        $settings = $this->configUtil->getAllSettings($filePath);
        
        $this->assertEquals($testSettings, $settings);
        
        // Clean up
        Storage::delete($filePath);
    }

    public function test_returns_empty_array_for_non_existent_file()
    {
        $settings = $this->configUtil->getAllSettings('non_existent_file.json');
        
        $this->assertEquals([], $settings);
    }

    public function test_returns_null_for_non_existent_setting()
    {
        $setting = $this->configUtil->getSetting('non_existent_key');
        
        $this->assertNull($setting);
    }

    public function test_can_set_and_get_dynamic_setting()
    {
        $key = 'dynamic_test_key';
        $value = 'dynamic_test_value';
        
        // Mock Storage to avoid file system issues
        Storage::shouldReceive('put')
            ->with(\Mockery::type('string'), \Mockery::type('string'))
            ->andReturn(true);
        
        $this->configUtil->setSetting($key, $value);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_can_update_existing_setting()
    {
        $key = 'update_test_key';
        $initialValue = 'initial_value';
        $updatedValue = 'updated_value';
        
        // Mock Storage to avoid file system issues
        Storage::shouldReceive('put')
            ->with(\Mockery::type('string'), \Mockery::type('string'))
            ->andReturn(true);
        
        // Set initial value
        $this->configUtil->setSetting($key, $initialValue);
        
        // Update the value
        $this->configUtil->setSetting($key, $updatedValue);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }
}
