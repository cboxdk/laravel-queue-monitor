<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobStartedAction;
use Illuminate\Queue\Events\JobProcessing;

final readonly class JobProcessingListener
{
    public function __construct(
        private RecordJobStartedAction $action,
    ) {}

    /**
     * Handle the event
     */
    public function handle(JobProcessing $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        try {
            $this->action->execute($event);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking queue operations
            report($e);
        }
    }
}
