<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Listeners;

use Illuminate\Queue\Events\JobProcessed;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;

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
