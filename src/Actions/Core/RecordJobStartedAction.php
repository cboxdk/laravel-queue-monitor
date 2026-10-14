<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\WorkerContextServiceContract;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RecordJobStartedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private WorkerContextServiceContract $workerContext,
        private RecordJobQueuedAction $recordQueuedAction,
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

        if ($job === null) {
            return;
        }

        $jobId = $this->getJobId($job);

        DB::transaction(function () use ($event, $job, $jobId): void {
            // Find the LATEST record for this job_id (most recent attempt)
            /** @var JobMonitor|null $jobMonitor */
            $jobMonitor = JobMonitor::query()->where('job_id', $jobId)
                ->orderByDesc('attempt')->orderByDesc('created_at')
                ->lockForUpdate()->first();

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

                // Create a NEW record for this attempt, preserving the previous attempt's data
                $this->repository->create(new JobMonitorData(
                    id: null,
                    uuid: Str::uuid()->toString(),
                    jobId: $jobId,
                    jobClass: $jobMonitor->job_class,
                    displayName: $jobMonitor->display_name,
                    connection: $jobMonitor->connection,
                    queue: $jobMonitor->queue,
                    payload: $jobMonitor->payload,
                    status: JobStatus::PROCESSING,
                    attempt: $attempt,
                    maxAttempts: $jobMonitor->max_attempts,
                    retriedFromId: $jobMonitor->id,
                    serverName: $workerContext->serverName,
                    workerId: $workerContext->workerId,
                    workerType: $workerContext->workerType->value,
                    cpuTimeMs: null,
                    memoryPeakMb: null,
                    fileDescriptors: null,
                    durationMs: null,
                    exception: null,
                    tags: $jobMonitor->tags,
                    queuedAt: $jobMonitor->queued_at,
                    availableAt: $jobMonitor->available_at,
                    startedAt: now(),
                    completedAt: null,
                    createdAt: now(),
                    updatedAt: now(),
                ));
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
     * Close the previous attempt before a redelivery starts a new one
     *
     * Laravel doesn't fire JobFailed for intermediate retries, so an attempt
     * that threw is still `processing` with an exception recorded: mark it
     * FAILED. An attempt that is still `processing` with no exception and was
     * not released never reported back at all — the worker was hard-killed
     * (SIGKILL, OOM, a supervisor timeout kill) and the broker redelivered the
     * message once its visibility timeout / retry_after lapsed. Mark it TIMEOUT,
     * the same terminal status stuck-job resolution uses for an attempt that
     * never finished. Its duration is left unknown: the time until redelivery
     * includes the broker's wait and would inflate duration statistics.
     */
    private function closeUnfinishedAttempt(JobMonitor $jobMonitor): void
    {
        if ($jobMonitor->exception_class !== null) {
            $this->repository->update($jobMonitor->uuid, [
                'status' => JobStatus::FAILED,
                'completed_at' => now(),
                'duration_ms' => $jobMonitor->started_at
                    ? (int) $jobMonitor->started_at->diffInMilliseconds(now())
                    : null,
            ]);

            return;
        }

        $this->repository->update($jobMonitor->uuid, [
            'status' => JobStatus::TIMEOUT,
            'completed_at' => now(),
            'duration_ms' => null,
        ]);
    }

    /**
     * Create job record when it starts processing (wasn't queued normally)
     */
    private function createFromProcessing(object $event): void
    {
        $job = $event->job ?? null;

        if ($job === null) {
            return;
        }

        // Use injected action instead of service locator
        $jobMonitor = $this->recordQueuedAction->execute($event);

        if ($jobMonitor === null) {
            return;
        }

        // Immediately update to processing
        $this->repository->update($jobMonitor->uuid, [
            'status' => JobStatus::PROCESSING,
            'job_id' => $this->getJobId($job),
            'started_at' => now(),
        ]);
    }

    /**
     * Get job ID from Laravel job instance
     *
     * The Job contract declares getJobId(): string, but the database queue
     * driver returns the integer auto-increment id, so normalize to string.
     */
    private function getJobId(Job $job): string
    {
        return (string) $job->getJobId();
    }
}
