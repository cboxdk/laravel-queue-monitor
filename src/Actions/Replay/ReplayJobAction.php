<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Replay;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobReplayData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Exceptions\JobNotFoundException;
use Cbox\LaravelQueueMonitor\Exceptions\JobReplayException;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class ReplayJobAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Replay a job from stored payload
     */
    public function execute(string $uuid): JobReplayData
    {
        if (! config('queue-monitor.enabled', true)) {
            throw JobReplayException::storageDisabled();
        }

        $jobMonitor = $this->repository->findByUuid($uuid);

        if ($jobMonitor === null) {
            throw JobNotFoundException::withUuid($uuid);
        }

        // Validate job can be replayed
        $this->validateReplayable($jobMonitor);

        $payload = $jobMonitor->payload;

        if ($payload === null) {
            throw JobReplayException::payloadNotStored($uuid);
        }

        // Dispatch job to original queue
        $payloadJson = json_encode($payload);

        if ($payloadJson === false) {
            throw JobReplayException::invalidPayload($uuid);
        }

        $newJobId = Queue::connection($jobMonitor->connection)
            ->pushRaw($payloadJson, $jobMonitor->queue);

        $newUuid = Str::uuid()->toString();

        return new JobReplayData(
            originalUuid: $uuid,
            newUuid: $newUuid,
            newJobId: is_string($newJobId) ? $newJobId : null,
            queue: $jobMonitor->queue,
            connection: $jobMonitor->connection,
            replayedAt: now(),
        );
    }

    /**
     * Validate that job can be replayed
     */
    private function validateReplayable(JobMonitor $jobMonitor): void
    {
        // Cannot replay jobs that are currently processing
        if ($jobMonitor->status === JobStatus::PROCESSING) {
            throw JobReplayException::jobProcessing($jobMonitor->uuid);
        }

        // Check if job class still exists
        if (! class_exists($jobMonitor->job_class)) {
            throw JobReplayException::jobClassNotFound($jobMonitor->job_class);
        }

        // Check if payload storage is enabled
        if (! config('queue-monitor.storage.store_payload', true)) {
            throw JobReplayException::storageDisabled();
        }
    }
}
