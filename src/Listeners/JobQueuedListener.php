<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use Illuminate\Queue\Events\JobQueued;

final class JobQueuedListener
{
    /**
     * Handle the event
     */
    public function handle(JobQueued $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        /** @var string $actionClass */
        $actionClass = config('queue-monitor.actions.record_job_queued', RecordJobQueuedAction::class);

        /** @var RecordJobQueuedAction $action */
        $action = app($actionClass);

        try {
            $action->execute($event);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking queue operations
            report($e);
        }
    }
}
