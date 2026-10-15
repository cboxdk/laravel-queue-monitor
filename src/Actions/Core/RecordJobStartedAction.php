<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\WorkerContextServiceContract;
use Cbox\LaravelQueueMonitor\Support\JobKey;
use Cbox\LaravelQueueMonitor\Support\ReleasedDatabaseJobs;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Str;

final readonly class RecordJobStartedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private WorkerContextServiceContract $workerContext,
        private RecordJobQueuedAction $recordQueuedAction,
        private ReleasedDatabaseJobs $releasedDatabaseJobs,
    ) {}

    /**
     * Record when a job starts processing
     *
     * When a job is retried (attempt > 1), a NEW record is created for each attempt
     * and linked to the original via retried_from_id. This preserves the full
     * attempts trail — every attempt's exception, duration, and metrics are kept,
     * including an attempt whose worker was killed before it could report back.
     * A released job (parked as `queued`) is the one exception: its redelivery
     * reuses the same row.
     *
     * Note: Caller (listener) is responsible for checking if monitoring is enabled.
     */
    public function execute(object $event): void
    {
        $job = $event->job ?? null;

        if (! $job instanceof Job) {
            return;
        }

        $jobId = JobKey::of($job);

        JobMonitor::transaction(function () use ($event, $job, $jobId): void {
            $jobMonitor = $this->latestRow($jobId);

            // A released database job comes back under a new job id; take over
            // the rows its release left for it (see ReleasedDatabaseJobs).
            if ($jobMonitor === null && $this->releasedDatabaseJobs->reclaim($job, $jobId)) {
                $jobMonitor = $this->latestRow($jobId);
            }

            if ($jobMonitor === null) {
                $this->createFromProcessing($event);

                return;
            }

            $attempt = $job->attempts();
            $workerContext = $this->workerContext->capture();

            if ($attempt > 1) {
                if ($jobMonitor->status === JobStatus::QUEUED) {
                    // The previous attempt released the job back onto the queue
                    // (rate limiting, middleware, a manual release) — the
                    // completed action parks a released attempt as `queued`.
                    // Update the same row in place so a release does not leave a
                    // phantom terminal row or a duplicate row per delivery.
                    $this->repository->update($jobMonitor->uuid, [
                        'status' => JobStatus::PROCESSING,
                        'attempt' => $attempt,
                        'started_at' => now(),
                        'server_name' => $workerContext->serverName,
                        'worker_id' => $workerContext->workerId,
                        'worker_type' => $workerContext->workerType->value,
                    ]);

                    return;
                }

                if (! $jobMonitor->status->isFinished()) {
                    $this->closeUnfinishedAttempt($jobMonitor);
                }

                $this->createAttemptRow($jobMonitor, $jobId, $attempt);
            } elseif ($jobMonitor->status->isFinished()) {
                // A first attempt whose row already has an outcome is a new run
                // of a finished job: `queue:retry` re-pushes a failed Redis job
                // with its original id and its attempts reset. Rewriting that
                // row would erase the failure it records, so the run gets a row
                // of its own, linked to the one it retries.
                $this->createAttemptRow($jobMonitor, $jobId, max(1, $attempt));
            } else {
                // First attempt: update the existing record
                $this->repository->update($jobMonitor->uuid, [
                    'status' => JobStatus::PROCESSING,
                    'job_id' => $jobId,
                    'attempt' => $attempt,
                    'started_at' => now(),
                    'server_name' => $workerContext->serverName,
                    'worker_id' => $workerContext->workerId,
                    'worker_type' => $workerContext->workerType->value,
                ]);
            }
        });
    }

    /**
     * The job's latest row, locked for the rest of the transaction
     *
     * Latest is the row created last, not the highest attempt: a Redis job
     * re-pushed by `queue:retry` keeps its id and starts again at attempt 1,
     * after rows of the previous run with higher attempt numbers.
     */
    private function latestRow(string $jobId): ?JobMonitor
    {
        /** @var JobMonitor|null */
        return JobMonitor::query()->where('job_id', $jobId)
            ->orderByDesc('id')
            ->lockForUpdate()->first();
    }

    /**
     * Close the previous attempt before a redelivery starts a new one
     *
     * An attempt that threw is closed as FAILED by JobExceptionOccurredListener
     * the moment it throws, so it is already finished here. A row still
     * `processing` with an exception was recorded by an earlier version, which
     * left intermediate failures open until the next attempt: mark it FAILED,
     * with its duration unknown, because the time until now includes the
     * backoff and the wait for redelivery.
     *
     * An attempt still `processing` with no exception and not released never
     * reported back at all — the worker was hard-killed (SIGKILL, OOM, a
     * supervisor timeout kill) and the broker redelivered the message once its
     * visibility timeout / retry_after lapsed. Mark it TIMEOUT, the same
     * terminal status stuck-job resolution uses for an attempt that never
     * finished. Its duration is left unknown for the same reason.
     */
    private function closeUnfinishedAttempt(JobMonitor $jobMonitor): void
    {
        $this->repository->update($jobMonitor->uuid, [
            'status' => $jobMonitor->exception_class !== null ? JobStatus::FAILED : JobStatus::TIMEOUT,
            'completed_at' => now(),
            'duration_ms' => null,
        ]);
    }

    /**
     * Create the row for a new attempt, linked to the row it follows
     *
     * Everything that describes the job itself (class, queue, payload, tags,
     * when it was queued) is carried over from the previous row; everything
     * that describes a run starts empty.
     */
    private function createAttemptRow(JobMonitor $previous, string $jobId, int $attempt): void
    {
        $workerContext = $this->workerContext->capture();

        $this->repository->create(new JobMonitorData(
            id: null,
            uuid: Str::uuid()->toString(),
            jobId: $jobId,
            jobClass: $previous->job_class,
            displayName: $previous->display_name,
            connection: $previous->connection,
            queue: $previous->queue,
            payload: $previous->payload,
            status: JobStatus::PROCESSING,
            attempt: $attempt,
            maxAttempts: $previous->max_attempts,
            retriedFromId: $previous->id,
            serverName: $workerContext->serverName,
            workerId: $workerContext->workerId,
            workerType: $workerContext->workerType->value,
            cpuTimeMs: null,
            memoryPeakMb: null,
            fileDescriptors: null,
            durationMs: null,
            exception: null,
            tags: $previous->tags,
            queuedAt: $previous->queued_at,
            availableAt: $previous->available_at,
            startedAt: now(),
            completedAt: null,
            createdAt: now(),
            updatedAt: now(),
        ));
    }

    /**
     * Create job record when it starts processing (wasn't queued normally)
     */
    private function createFromProcessing(object $event): void
    {
        $job = $event->job ?? null;

        if (! $job instanceof Job) {
            return;
        }

        // Use injected action instead of service locator
        $jobMonitor = $this->recordQueuedAction->execute($event);

        if ($jobMonitor === null) {
            return;
        }

        // Immediately update to processing. The queued action records every
        // new row as attempt 1; a job first seen on a later delivery (monitoring
        // enabled mid-flight, a message pushed by another app) is on the attempt
        // the broker reports, and completion is matched to the row by it.
        $this->repository->update($jobMonitor->uuid, [
            'status' => JobStatus::PROCESSING,
            'job_id' => JobKey::of($job),
            'attempt' => max(1, $job->attempts()),
            'started_at' => now(),
        ]);
    }
}
