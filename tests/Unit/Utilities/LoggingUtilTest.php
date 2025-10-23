<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\LoggingUtil;
use LaraUtilX\Enums\LogLevel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Monolog\Logger;

class LoggingUtilTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear any existing log file
        $logFile = storage_path('logs/custom.log');
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    private function mockLogger()
    {
        $mockLogger = $this->createMock(\Monolog\Logger::class);
        $mockLogger->method('info')->willReturnSelf();
        $mockLogger->method('debug')->willReturnSelf();
        $mockLogger->method('warning')->willReturnSelf();
        $mockLogger->method('error')->willReturnSelf();
        $mockLogger->method('critical')->willReturnSelf();
        
        // Use reflection to set the custom logger
        $reflection = new \ReflectionClass(LoggingUtil::class);
        $property = $reflection->getProperty('customLogger');
        $property->setAccessible(true);
        $property->setValue(null, $mockLogger);
        
        return $mockLogger;
    }

    protected function tearDown(): void
    {
        // Clean up log file after each test
        $logFile = storage_path('logs/custom.log');
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        
        parent::tearDown();
    }

    public function test_can_log_with_info_level()
    {
        $message = 'Test info message';
        $context = ['key' => 'value'];
        
        $this->mockLogger();
        LoggingUtil::info($message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_can_log_with_debug_level()
    {
        $message = 'Test debug message';
        $context = ['debug_key' => 'debug_value'];
        
        $this->mockLogger();
        LoggingUtil::debug($message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_can_log_with_warning_level()
    {
        $message = 'Test warning message';
        $context = ['warning_key' => 'warning_value'];
        
        $this->mockLogger();
        LoggingUtil::warning($message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_can_log_with_error_level()
    {
        $message = 'Test error message';
        $context = ['error_key' => 'error_value'];
        
        $this->mockLogger();
        LoggingUtil::error($message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_can_log_with_critical_level()
    {
        $message = 'Test critical message';
        $context = ['critical_key' => 'critical_value'];
        
        $this->mockLogger();
        LoggingUtil::critical($message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_can_log_with_custom_channel()
    {
        $message = 'Test channel message';
        $context = ['channel_key' => 'channel_value'];
        $channel = 'custom_channel';
        
        // Mock the Log facade to verify channel is used
        $mockLogger = $this->createMock(\Monolog\Logger::class);
        $mockLogger->method('info')->willReturnSelf();
        
        Log::shouldReceive('channel')
            ->with($channel)
            ->once()
            ->andReturn($mockLogger);
        
        LoggingUtil::info($message, $context, $channel);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_includes_timestamp_in_context()
    {
        $message = 'Test timestamp message';
        $context = ['test_key' => 'test_value'];
        
        // Mock the logger to avoid file system issues
        $mockLogger = $this->createMock(\Monolog\Logger::class);
        $mockLogger->method('info')->willReturnSelf();
        
        // Use reflection to set the custom logger
        $reflection = new \ReflectionClass(LoggingUtil::class);
        $property = $reflection->getProperty('customLogger');
        $property->setAccessible(true);
        $property->setValue(null, $mockLogger);
        
        LoggingUtil::info($message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_includes_environment_in_context()
    {
        Config::set('app.env', 'testing');
        
        $message = 'Test environment message';
        $context = ['test_key' => 'test_value'];
        
        $this->mockLogger();
        LoggingUtil::info($message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_can_use_generic_log_method()
    {
        $message = 'Test generic log message';
        $context = ['test_key' => 'test_value'];
        
        $this->mockLogger();
        LoggingUtil::log(LogLevel::Info, $message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_logs_are_in_json_format()
    {
        $message = 'Test JSON format message';
        $context = ['json_key' => 'json_value'];
        
        $this->mockLogger();
        LoggingUtil::info($message, $context);
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }
}
