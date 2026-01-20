<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;
use Illuminate\Queue\Events\JobProcessed;

final readonly class JobProcessedListener
{
    public function __construct(
        private RecordJobCompletedAction $action,
    ) {}

    /**
     * Handle the event
     */
    public function handle(JobProcessed $event): void
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
