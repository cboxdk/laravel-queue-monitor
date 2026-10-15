<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Jobs\StoreJobTagsJob;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Cbox\LaravelQueueMonitor\Support\JobAttemptResolver;
use Cbox\LaravelQueueMonitor\Support\ReleasedDatabaseJobs;
use Illuminate\Contracts\Queue\Job;

final readonly class RecordJobCompletedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private TagRepositoryContract $tagRepository,
        private JobAttemptResolver $attempts,
        private ReleasedDatabaseJobs $releasedDatabaseJobs,
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
            $this->attempts->attemptEnded($job, $jobMonitor);
            $this->parkReleasedAttempt($job, $jobMonitor);

            return;
        }

        $completedAt = now();
        $durationMs = $this->attempts->durationMs($job, $jobMonitor, $completedAt);
        $this->attempts->attemptEnded($job, $jobMonitor);

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
     * Park a released attempt as `queued`, unless a redelivery superseded it
     *
     * The check and the update run under the row lock the started action
     * takes on the job's latest row before it decides between reusing a
     * parked row and opening a new one. Without it a redelivery could start
     * between the check and the update: it would see this row `processing`,
     * close it and open its own, and the update would then park the closed
     * row as `queued` forever.
     *
     * A database job comes back under a new job id, so its rows are re-keyed
     * for the redelivery to find (see ReleasedDatabaseJobs).
     */
    private function parkReleasedAttempt(Job $job, JobMonitor $jobMonitor): void
    {
        JobMonitor::transaction(function () use ($job, $jobMonitor): void {
            /** @var JobMonitor|null $locked */
            $locked = JobMonitor::query()->whereKey($jobMonitor->getKey())
                ->lockForUpdate()->first();

            if ($locked === null || $this->attempts->isSuperseded($locked)) {
                return;
            }

            $this->repository->update($locked->uuid, [
                'status' => JobStatus::QUEUED,
            ]);

            $this->releasedDatabaseJobs->markReleased($job);
        });
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
}
