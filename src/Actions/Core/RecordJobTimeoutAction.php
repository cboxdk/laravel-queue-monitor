<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Carbon\CarbonInterface;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Support\JobAttemptResolver;
use Illuminate\Contracts\Queue\Job;

final readonly class RecordJobTimeoutAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private JobAttemptResolver $attempts,
    ) {}

    /**
     * Record when a job times out
     *
     * Note: Caller (listener) is responsible for checking if monitoring is enabled.
     */
    public function execute(object $event): void
    {
        $job = $event->job ?? null;

        if (! $job instanceof Job) {
            return;
        }

        // The row of the attempt this worker ran, not the latest attempt: a
        // redelivery may have started a newer attempt while this one ran.
        $jobMonitor = $this->attempts->rowFor($job);

        if ($jobMonitor === null) {
            return;
        }

        $completedAt = now();
        $durationMs = $this->calculateDuration($jobMonitor->started_at, $completedAt);

        $this->repository->update($jobMonitor->uuid, [
            'status' => JobStatus::TIMEOUT,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
            'exception_class' => 'JobTimeout',
            'exception_message' => 'Job exceeded maximum execution time',
        ]);
    }

    /**
     * Calculate duration in milliseconds
     */
    private function calculateDuration(?CarbonInterface $startedAt, CarbonInterface $completedAt): ?int
    {
        if ($startedAt === null) {
            return null;
        }

        return (int) $startedAt->diffInMilliseconds($completedAt);
    }
}
