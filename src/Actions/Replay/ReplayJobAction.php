<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Replay;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobReplayData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Exceptions\JobNotFoundException;
use Cbox\LaravelQueueMonitor\Exceptions\JobReplayException;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Support\QueueName;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

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

        // Build fresh payload with new identity so the job isn't deduplicated
        $newUuid = Str::uuid()->toString();
        $newId = Str::uuid()->toString();

        /** @var array<string, mixed> $freshPayload */
        $freshPayload = $payload;
        $freshPayload['uuid'] = $newUuid;
        $freshPayload['id'] = $newId;
        $freshPayload['attempts'] = 0;

        $payloadJson = json_encode($freshPayload);

        if ($payloadJson === false) {
            throw JobReplayException::invalidPayload($uuid);
        }

        $queueJobId = Queue::connection($jobMonitor->connection)
            ->pushRaw($payloadJson, $jobMonitor->queue, $this->pushOptions($jobMonitor));

        return new JobReplayData(
            originalUuid: $uuid,
            newUuid: $newUuid,
            newJobId: is_string($queueJobId) ? $queueJobId : $newId,
            queue: $jobMonitor->queue,
            connection: $jobMonitor->connection,
            replayedAt: now(),
        );
    }

    /**
     * Driver options for the raw push.
     *
     * A raw push skips SqsQueue::getQueueableOptions(), and SQS rejects a send
     * to a FIFO queue without a MessageGroupId and MessageDeduplicationId. This
     * applies Laravel's own defaults for a job that declares neither: the queue
     * name as the group and a fresh ordered UUID as the deduplication id, so a
     * replay is never deduplicated against the original send.
     *
     * @return array{MessageGroupId?: string, MessageDeduplicationId?: string}
     */
    private function pushOptions(JobMonitor $jobMonitor): array
    {
        if (! QueueName::isSqsFifo($jobMonitor->connection, $jobMonitor->queue)) {
            return [];
        }

        return [
            'MessageGroupId' => QueueName::normalize($jobMonitor->connection, $jobMonitor->queue),
            'MessageDeduplicationId' => (string) Str::orderedUuid(),
        ];
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
        if (! config('queue-monitor.storage.store_payload', app()->environment('local'))) {
            throw JobReplayException::storageDisabled();
        }
    }
}
