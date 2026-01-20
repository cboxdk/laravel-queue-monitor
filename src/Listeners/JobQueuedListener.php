<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use Illuminate\Queue\Events\JobQueued;

final readonly class JobQueuedListener
{
    public function __construct(
        private RecordJobQueuedAction $action,
    ) {}

    /**
     * Handle the event
     */
    public function handle(JobQueued $event): void
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
