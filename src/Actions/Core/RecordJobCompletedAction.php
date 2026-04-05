<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Jobs\StoreJobTagsJob;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;

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
        $jobMonitor = $this->repository->findLatestAttemptByJobId($jobId);

        if ($jobMonitor === null) {
            return;
        }

        $completedAt = now();
        $durationMs = $this->calculateDuration($jobMonitor->started_at, $completedAt);

        // Note: cpu_time_ms and memory_peak_mb are set by QueueMetricsSubscriber
        // from laravel-queue-metrics' process-level instrumentation (ProcessMetrics).
        // We do NOT set them here to avoid overwriting accurate data with imprecise fallbacks.

        $this->repository->update($jobMonitor->uuid, [
            'status' => JobStatus::COMPLETED,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
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

        if (config('queue-monitor.storage.deferred_tags', false)) {
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
