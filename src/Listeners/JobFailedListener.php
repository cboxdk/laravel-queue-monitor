<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobFailedAction;
use Illuminate\Queue\Events\JobFailed;

final class JobFailedListener
{
    /**
     * Handle the event
     */
    public function handle(JobFailed $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        /** @var string $actionClass */
        $actionClass = config('queue-monitor.actions.record_job_failed', RecordJobFailedAction::class);

        /** @var RecordJobFailedAction $action */
        $action = app($actionClass);

        try {
            $action->execute($event);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking queue operations
            report($e);
        }
    }
}
