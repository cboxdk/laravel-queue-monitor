<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Core;

use Illuminate\Contracts\Queue\Job;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use PHPeek\LaravelQueueMonitor\Services\WorkerContextService;

final readonly class RecordJobStartedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private WorkerContextService $workerContext,
    ) {}

    /**
     * Record when a job starts processing
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

        $jobId = $this->getJobId($job);
        $jobMonitor = $this->repository->findByJobId($jobId);

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

        $action = app(RecordJobQueuedAction::class);
        $jobMonitor = $action->execute($event);

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
