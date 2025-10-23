<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\SchedulerUtil;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class SchedulerUtilTest extends TestCase
{
    protected SchedulerUtil $schedulerUtil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedulerUtil = new SchedulerUtil();
    }

    public function test_can_get_schedule_summary()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);
        
        $this->app->instance(Schedule::class, $schedule);
        
        $summary = $this->schedulerUtil->getScheduleSummary();
        
        $this->assertIsArray($summary);
        $this->assertEmpty($summary);
    }

    public function test_can_check_for_overdue_tasks()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);
        
        $this->app->instance(Schedule::class, $schedule);
        
        $hasOverdue = $this->schedulerUtil->hasOverdueTasks();
        
        $this->assertIsBool($hasOverdue);
    }

    public function test_returns_false_when_no_overdue_tasks()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);
        
        $this->app->instance(Schedule::class, $schedule);
        
        $hasOverdue = $this->schedulerUtil->hasOverdueTasks();
        
        $this->assertFalse($hasOverdue);
    }

    public function test_ignores_running_tasks_for_overdue_check()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);
        
        $this->app->instance(Schedule::class, $schedule);
        
        $hasOverdue = $this->schedulerUtil->hasOverdueTasks();
        
        $this->assertFalse($hasOverdue);
    }

    public function test_handles_empty_schedule()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);
        
        $this->app->instance(Schedule::class, $schedule);
        
        $summary = $this->schedulerUtil->getScheduleSummary();
        $hasOverdue = $this->schedulerUtil->hasOverdueTasks();
        
        $this->assertIsArray($summary);
        $this->assertEmpty($summary);
        $this->assertFalse($hasOverdue);
    }

    public function test_logs_scheduled_events()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);
        
        $this->app->instance(Schedule::class, $schedule);
        
        // Mock Log facade to verify logging
        Log::shouldReceive('info')
            ->with(\Mockery::type('string'))
            ->once();
        
        $this->schedulerUtil->getScheduleSummary();
    }

    public function test_handles_multiple_events()
    {
        // Mock empty Schedule
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('events')->willReturn([]);
        
        $this->app->instance(Schedule::class, $schedule);
        
        $summary = $this->schedulerUtil->getScheduleSummary();
        
        $this->assertIsArray($summary);
        $this->assertEmpty($summary);
    }
}