<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\WorkerContextService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\DB;

final readonly class RecordJobStartedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private WorkerContextService $workerContext,
        private RecordJobQueuedAction $recordQueuedAction,
    ) {}

    /**
     * Record when a job starts processing
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

        // Use transaction with pessimistic locking to prevent race conditions
        DB::transaction(function () use ($event, $job, $jobId): void {
            // Lock the row if it exists to prevent concurrent updates
            /** @var JobMonitor|null $jobMonitor */
            $jobMonitor = JobMonitor::query()->where('job_id', $jobId)->lockForUpdate()->first();

            if ($jobMonitor === null) {
                // Job wasn't queued through normal means, create record now
                $this->createFromProcessing($event);

                return;
            }

            $workerContext = $this->workerContext->capture();

            $this->repository->update($jobMonitor->uuid, [
                'status' => JobStatus::PROCESSING,
                'job_id' => $jobId,
                'attempt' => $job->attempts(),
                'started_at' => now(),
                'server_name' => $workerContext->serverName,
                'worker_id' => $workerContext->workerId,
                'worker_type' => $workerContext->workerType->value,
            ]);
        });
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

        // Immediately update to processing
        $this->repository->update($jobMonitor->uuid, [
            'status' => JobStatus::PROCESSING,
            'job_id' => $this->getJobId($job),
            'started_at' => now(),
        ]);
    }

    /**
     * Get job ID from Laravel job instance
     */
    private function getJobId(Job $job): string
    {
        return $job->getJobId();
    }
}
