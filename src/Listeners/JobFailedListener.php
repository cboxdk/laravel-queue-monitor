<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Listeners;

use Illuminate\Queue\Events\JobFailed;
use PHPeek\LaravelQueueMonitor\Actions\Core\RecordJobFailedAction;

final readonly class JobFailedListener
{
    public function __construct(
        private RecordJobFailedAction $action,
    ) {}

    /**
     * Handle the event
     */
    public function handle(JobFailed $event): void
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
