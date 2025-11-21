<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Core;

use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Events\JobCancelled;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class CancelJobAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Cancel a job
     */
    public function execute(string $uuid): bool
    {
        if (! config('queue-monitor.enabled', true)) {
            return false;
        }

        $jobMonitor = $this->repository->findByUuid($uuid);

        if ($jobMonitor === null) {
            return false;
        }

        // Can only cancel queued or processing jobs
        if (! in_array($jobMonitor->status, [JobStatus::QUEUED, JobStatus::PROCESSING])) {
            return false;
        }

        $this->repository->update($uuid, [
            'status' => JobStatus::CANCELLED,
            'completed_at' => now(),
        ]);

        $jobMonitor->refresh();
        event(new JobCancelled($jobMonitor));

        return true;
    }
}
