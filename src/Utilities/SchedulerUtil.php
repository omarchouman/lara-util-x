<?php

namespace LaraUtilX\Utilities;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Inspect the application's scheduled tasks.
 *
 * These methods read the Schedule bound in the container, which is only
 * populated in a console context. Laravel registers schedules through
 * Artisan::starting() (withSchedule) and afterResolving(ConsoleKernel)
 * (routes/console.php), and neither fires during a web request, so calling
 * these from an HTTP route returns an empty schedule rather than an error.
 * Use them from Artisan commands, queued jobs dispatched by the scheduler, or
 * anywhere else the console kernel has booted.
 */
class SchedulerUtil
{
    /**
     * Get a summary of the scheduled tasks.
     *
     * @return array
     */
    public function getScheduleSummary()
    {
        return collect($this->events())->map(function (Event $event) {
            return [
                'command' => $event->command,
                'expression' => $event->expression,
                'description' => $event->description,
                'next_run' => $event->nextRunDate(),
                'is_due' => $this->isDue($event),
                'is_running' => $this->isRunning($event),
                'output' => $event->output,
            ];
        })->toArray();
    }

    /**
     * Determine whether an event is due to run now.
     *
     * Laravel's Event::isDue() needs the application instance; there is no
     * getNextRunDate(), and comparing nextRunDate() against now never matches
     * because that date is always in the future.
     */
    public function isDue(Event $event): bool
    {
        return $event->isDue(app());
    }

    /**
     * Determine whether an event is currently running.
     *
     * Only events using withoutOverlapping() hold a mutex, so anything else
     * reports false rather than throwing.
     */
    public function isRunning(Event $event): bool
    {
        if (! $event->withoutOverlapping) {
            return false;
        }

        return $event->mutex->exists($event);
    }

    /**
     * Check whether any scheduled task is due this minute and not already
     * running.
     *
     * This is "due now", not "overdue". Laravel's Event::isDue() asks whether
     * the cron expression matches the current minute, so an everyMinute() task
     * makes this true almost constantly. Detecting genuinely overdue work would
     * mean recording last-run times, which the scheduler does not keep.
     *
     * @return bool
     */
    public function hasDueTasks()
    {
        return collect($this->events())->contains(function (Event $event) {
            return $this->isDue($event) && ! $this->isRunning($event);
        });
    }

    /**
     * @deprecated 1.5.5 Use hasDueTasks(). The name promised overdue detection
     *             that this never performed.
     * @return bool
     */
    public function hasOverdueTasks()
    {
        return $this->hasDueTasks();
    }

    /**
     * @return array<int, Event>
     */
    private function events(): array
    {
        return app(Schedule::class)->events();
    }
}
