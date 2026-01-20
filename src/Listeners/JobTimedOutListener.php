<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobTimeoutAction;
use Illuminate\Queue\Events\JobTimedOut;

final readonly class JobTimedOutListener
{
    public function __construct(
        private RecordJobTimeoutAction $action,
    ) {}

    /**
     * Handle the event
     */
    public function handle(JobTimedOut $event): void
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
