<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

final class JobMonitorRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JobMonitor $jobMonitor,
    ) {}
}
