<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Support;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Queued when ListenedEvent fires, which wraps it in CallQueuedListener.
 */
class QueuedListener implements ShouldQueue
{
    public function handle(ListenedEvent $event): void
    {
        //
    }
}
