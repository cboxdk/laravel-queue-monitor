<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use Illuminate\Queue\Events\JobProcessing;

final class JobProcessingListener
{
    /**
     * Handle the event
     */
    public function handle(JobProcessing $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        /** @var string $actionClass */
        $actionClass = config('queue-monitor.actions.record_job_started', RecordJobStartedAction::class);

        /** @var RecordJobStartedAction $action */
        $action = app($actionClass);

        try {
            $action->execute($event);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking queue operations
            report($e);
        }
    }
}
