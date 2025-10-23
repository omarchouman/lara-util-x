<?php

namespace LaraUtilX\Tests\Unit\Enums;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Enums\LogLevel;

class LogLevelTest extends TestCase
{
    public function test_log_level_enum_has_correct_values()
    {
        $this->assertEquals('debug', LogLevel::Debug->value);
        $this->assertEquals('info', LogLevel::Info->value);
        $this->assertEquals('warning', LogLevel::Warning->value);
        $this->assertEquals('error', LogLevel::Error->value);
        $this->assertEquals('critical', LogLevel::Critical->value);
    }

    public function test_log_level_enum_can_be_used_in_switch()
    {
        $level = LogLevel::Info;
        
        $result = match ($level) {
            LogLevel::Debug => 'debug',
            LogLevel::Info => 'info',
            LogLevel::Warning => 'warning',
            LogLevel::Error => 'error',
            LogLevel::Critical => 'critical',
        };
        
        $this->assertEquals('info', $result);
    }

    public function test_log_level_enum_can_be_serialized()
    {
        $level = LogLevel::Error;
        $serialized = serialize($level);
        $unserialized = unserialize($serialized);
        
        $this->assertEquals($level, $unserialized);
    }

    public function test_log_level_enum_can_be_compared()
    {
        $level1 = LogLevel::Info;
        $level2 = LogLevel::Info;
        $level3 = LogLevel::Error;
        
        $this->assertTrue($level1 === $level2);
        $this->assertFalse($level1 === $level3);
    }

    public function test_all_log_levels_are_available()
    {
        $expectedLevels = ['debug', 'info', 'warning', 'error', 'critical'];
        $actualLevels = array_map(fn($case) => $case->value, LogLevel::cases());
        
        $this->assertEquals($expectedLevels, $actualLevels);
    }

    public function test_log_level_enum_has_string_value()
    {
        $level = LogLevel::Warning;
        
        $this->assertEquals('warning', $level->value);
        $this->assertIsString($level->value);
    }
}
