<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Core;

use Carbon\Carbon;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;

final readonly class RecordJobCompletedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private TagRepositoryContract $tagRepository,
    ) {}

    /**
     * Record when a job completes successfully
     */
    public function execute(object $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        $job = $event->job ?? null;

        if ($job === null) {
            return;
        }

        $jobId = $job->getJobId();
        $jobMonitor = $this->repository->findByJobId($jobId);

        if ($jobMonitor === null) {
            return;
        }

        $completedAt = now();
        $durationMs = $this->calculateDuration($jobMonitor->started_at, $completedAt);

        // Capture basic resource metrics
        $memoryPeakMb = memory_get_peak_usage(true) / 1024 / 1024;

        $this->repository->update($jobMonitor->uuid, [
            'status' => JobStatus::COMPLETED,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
            'memory_peak_mb' => round($memoryPeakMb, 2),
        ]);

        // Store normalized tags if present
        if ($jobMonitor->tags !== null && count($jobMonitor->tags) > 0) {
            $this->tagRepository->storeTags($jobMonitor->id, $jobMonitor->tags);
        }
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
