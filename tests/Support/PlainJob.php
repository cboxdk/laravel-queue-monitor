<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A job with no displayName() method, so its payload displayName is its class.
 */
class PlainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        //
    }
}
