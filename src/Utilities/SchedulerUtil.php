<?php

namespace LaraUtilX\Utilities;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

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
     * Check if any scheduled tasks are due and not already running.
     *
     * @return bool
     */
    public function hasOverdueTasks()
    {
        return collect($this->events())->contains(function (Event $event) {
            return $this->isDue($event) && ! $this->isRunning($event);
        });
    }

    /**
     * @return array<int, Event>
     */
    private function events(): array
    {
        return app(Schedule::class)->events();
    }
}
