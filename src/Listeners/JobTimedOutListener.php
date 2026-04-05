<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobTimeoutAction;
use Illuminate\Queue\Events\JobTimedOut;

final class JobTimedOutListener
{
    /**
     * Handle the event
     */
    public function handle(JobTimedOut $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        /** @var string $actionClass */
        $actionClass = config('queue-monitor.actions.record_job_timeout', RecordJobTimeoutAction::class);

        /** @var RecordJobTimeoutAction $action */
        $action = app($actionClass);

        try {
            $action->execute($event);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking queue operations
            report($e);
        }
    }
}
