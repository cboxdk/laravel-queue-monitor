<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobReplayData;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

final class JobReplayRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JobMonitor $originalJob,
        public readonly JobReplayData $replayData,
    ) {}
}
