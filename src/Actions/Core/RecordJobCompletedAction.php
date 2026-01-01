<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Core;

use Carbon\Carbon;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Jobs\StoreJobTagsJob;
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
        $this->storeTagsIfPresent($jobMonitor->id, $jobMonitor->tags);
    }

    /**
     * Store tags either synchronously or via deferred job
     *
     * @param  array<string>|null  $tags
     */
    private function storeTagsIfPresent(int $jobMonitorId, ?array $tags): void
    {
        if ($tags === null || count($tags) === 0) {
            return;
        }

        if (config('queue-monitor.deferred_tag_storage', false)) {
            StoreJobTagsJob::dispatch($jobMonitorId, $tags);
        } else {
            $this->tagRepository->storeTags($jobMonitorId, $tags);
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
