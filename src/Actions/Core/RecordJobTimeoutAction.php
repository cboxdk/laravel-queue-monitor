<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class RecordJobTimeoutAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Record when a job times out
     *
     * Note: Caller (listener) is responsible for checking if monitoring is enabled.
     */
    public function execute(object $event): void
    {
        $job = $event->job ?? null;

        if ($job === null) {
            return;
        }

        $jobId = $job->getJobId();
        // Use the latest attempt, matching completed/failed recording — a
        // first-match lookup would mark an earlier attempt's row on a retry.
        $jobMonitor = $this->repository->findLatestAttemptByJobId($jobId);

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
    private function calculateDuration(?Carbon $startedAt, Carbon $completedAt): ?int
    {
        if ($startedAt === null) {
            return null;
        }

        return (int) $startedAt->diffInMilliseconds($completedAt);
    }
}
