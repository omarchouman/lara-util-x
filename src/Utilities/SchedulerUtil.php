<?php

namespace LaraUtilX\Utilities;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;

class SchedulerUtil
{
    /**
     * Get a summary of the scheduled tasks.
     *
     * @return array
     */
    public function getScheduleSummary()
    {
        $schedule = app(Schedule::class);

        Log::info('Scheduled Events: ' . print_r($schedule->events(), true));

        return collect($schedule->events())->map(function (Event $event) {
            return [
                'command' => $event->command,
                'expression' => $event->expression,
                'description' => $event->description,
                'next_run' => $event->nextRunDate(),
                'is_due' => $this->isDue($event),
                'is_running' => $event->isRunning(),
                'output' => $event->output,
            ];
        })->toArray();
    }

    /**
     * Check if any scheduled tasks are overdue.
     *
     * @param  Event  $event
     * @return bool
     */
    private function isDue(Event $event)
    {
        $nextRunDate = $event->getNextRunDate();

        return $nextRunDate <= Carbon::now();
    }

    /**
     * Check if any scheduled tasks are overdue.
     *
     * @return bool
     */
    public function hasOverdueTasks()
    {
        $schedule = app(Schedule::class);

        return collect($schedule->events())->filter(function (Event $event) {
            return $this->isDue($event) && !$event->isRunning();
        })->isNotEmpty();
    }
}