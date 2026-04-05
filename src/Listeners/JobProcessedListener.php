<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;
use Illuminate\Queue\Events\JobProcessed;

final class JobProcessedListener
{
    /**
     * Handle the event
     */
    public function handle(JobProcessed $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        /** @var string $actionClass */
        $actionClass = config('queue-monitor.actions.record_job_completed', RecordJobCompletedAction::class);

        /** @var RecordJobCompletedAction $action */
        $action = app($actionClass);

        try {
            $action->execute($event);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking queue operations
            report($e);
        }
    }
}
