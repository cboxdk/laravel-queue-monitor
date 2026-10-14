<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Carbon\CarbonInterface;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Jobs\StoreJobTagsJob;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Cbox\LaravelQueueMonitor\Support\JobAttemptResolver;
use Illuminate\Contracts\Queue\Job;

final readonly class RecordJobCompletedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private TagRepositoryContract $tagRepository,
        private JobAttemptResolver $attempts,
    ) {}

    /**
     * Record when a job completes successfully
     *
     * The outcome is written to the row of the attempt this worker ran (see
     * JobAttemptResolver). That row may already be closed: a slow attempt the
     * broker redelivered was closed as TIMEOUT when the redelivery started, on
     * the assumption that its worker had been killed. The worker was in fact
     * still running, and its real outcome replaces that inference.
     *
     * Note: Caller (listener) is responsible for checking if monitoring is enabled.
     */
    public function execute(object $event): void
    {
        $job = $event->job ?? null;

        if (! $job instanceof Job) {
            return;
        }

        $jobMonitor = $this->attempts->rowFor($job);

        if ($jobMonitor === null) {
            return;
        }

        // A released job (rate limiting, middleware, or a manual release) still
        // fires JobProcessed, but the attempt did not complete — it will run
        // again. Recording it as `completed` inflates completion counts. Park the
        // row as `queued` instead: it is back on the queue, and it lets the
        // redelivery tell a release (reuse this row) from a worker that was
        // killed mid-attempt (still `processing`, close it and start a new row).
        //
        // An attempt that a redelivery has already superseded is left as it
        // is: the next delivery continues on the newer attempt's row, so
        // parking this one as `queued` would leave it queued forever.
        if ($job->isReleased()) {
            if ($this->attempts->isSuperseded($jobMonitor)) {
                return;
            }

            $this->repository->update($jobMonitor->uuid, [
                'status' => JobStatus::QUEUED,
            ]);

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
    private function calculateDuration(?CarbonInterface $startedAt, CarbonInterface $completedAt): ?int
    {
        if ($startedAt === null) {
            return null;
        }

        return (int) $startedAt->diffInMilliseconds($completedAt);
    }
}
