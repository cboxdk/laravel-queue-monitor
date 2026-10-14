<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use Cbox\LaravelQueueMonitor\Support\InFlightAttempts;
use Cbox\LaravelQueueMonitor\Support\JobKey;
use Illuminate\Queue\Events\JobProcessing;

final class JobProcessingListener
{
    public function __construct(
        private readonly InFlightAttempts $inFlightAttempts,
    ) {}

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
            // Remember which attempt this process is running, so per-job
            // metrics reported at the end of the job land on this attempt's
            // row even if the job was redelivered to another worker meanwhile.
            $this->inFlightAttempts->remember(JobKey::of($event->job), $event->job->attempts());
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            $action->execute($event);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking queue operations
            report($e);
        }
    }
}
