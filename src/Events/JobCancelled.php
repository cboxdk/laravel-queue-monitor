<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

final class JobCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JobMonitor $jobMonitor,
    ) {}
}
