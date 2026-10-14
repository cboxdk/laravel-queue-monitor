<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Dispatches an ExampleJob synchronously from inside its own handle(), so two
 * sync jobs are in flight at once — the outer one still processing while the
 * inner one starts and finishes.
 */
class NestedSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public bool $failAfterInner = false,
    ) {}

    public function handle(): void
    {
        dispatch_sync(new ExampleJob('inner'));

        if ($this->failAfterInner) {
            throw new \RuntimeException('Outer job failed after the inner job completed');
        }
    }
}
