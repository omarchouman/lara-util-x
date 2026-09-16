<?php

namespace LaraUtilX\Tests\Unit\Utilities;

use Illuminate\Console\Scheduling\Schedule;
use LaraUtilX\Tests\TestCase;
use LaraUtilX\Utilities\SchedulerUtil;

class SchedulerUtilTest extends TestCase
{
    protected SchedulerUtil $schedulerUtil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedulerUtil = new SchedulerUtil();
    }

    private function schedule(): Schedule
    {
        return $this->app->make(Schedule::class);
    }

    // -----------------------------------------------------------------------
    // Empty schedule
    // -----------------------------------------------------------------------

    public function test_handles_empty_schedule()
    {
        $summary = $this->schedulerUtil->getScheduleSummary();

        $this->assertIsArray($summary);
        $this->assertEmpty($summary);
        $this->assertFalse($this->schedulerUtil->hasOverdueTasks());
    }

    // -----------------------------------------------------------------------
    // Real events
    // -----------------------------------------------------------------------

    public function test_summarises_a_real_event()
    {
        $this->schedule()->command('list')->everyMinute();

        $summary = $this->schedulerUtil->getScheduleSummary();

        $this->assertCount(1, $summary);
        $this->assertEquals('* * * * *', $summary[0]['expression']);
        $this->assertArrayHasKey('next_run', $summary[0]);
        $this->assertIsBool($summary[0]['is_due']);
        $this->assertIsBool($summary[0]['is_running']);
    }

    public function test_summarises_multiple_events()
    {
        $this->schedule()->command('list')->everyMinute();
        $this->schedule()->command('inspire')->daily();

        $this->assertCount(2, $this->schedulerUtil->getScheduleSummary());
    }

    public function test_an_every_minute_event_is_due()
    {
        $this->schedule()->command('list')->everyMinute();

        $summary = $this->schedulerUtil->getScheduleSummary();

        $this->assertTrue($summary[0]['is_due']);
    }

    public function test_has_overdue_tasks_detects_a_due_event()
    {
        $this->schedule()->command('list')->everyMinute();

        // Before 1.5.4 this compared nextRunDate() against now, which is always
        // in the future, so it could never report true.
        $this->assertTrue($this->schedulerUtil->hasOverdueTasks());
    }

    public function test_has_overdue_tasks_is_false_when_nothing_is_due()
    {
        // Runs once a year, so it is almost never due.
        $this->schedule()->command('inspire')->yearlyOn(1, 1, '00:00');

        $summary = $this->schedulerUtil->getScheduleSummary();

        $this->assertFalse($summary[0]['is_due']);
        $this->assertFalse($this->schedulerUtil->hasOverdueTasks());
    }

    public function test_event_without_overlapping_protection_is_not_running()
    {
        $this->schedule()->command('list')->everyMinute();

        $events = $this->schedule()->events();

        $this->assertFalse($this->schedulerUtil->isRunning($events[0]));
    }

    public function test_event_with_overlapping_protection_does_not_throw()
    {
        $this->schedule()->command('inspire')->daily()->withoutOverlapping();

        $events = $this->schedule()->events();

        $this->assertIsBool($this->schedulerUtil->isRunning($events[0]));
    }

    public function test_next_run_is_in_the_future()
    {
        $this->schedule()->command('inspire')->daily();

        $summary = $this->schedulerUtil->getScheduleSummary();

        $this->assertGreaterThan(now()->subMinute(), $summary[0]['next_run']);
    }
}
