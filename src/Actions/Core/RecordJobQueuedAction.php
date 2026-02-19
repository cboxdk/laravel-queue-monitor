<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\WorkerContextService;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RecordJobQueuedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private WorkerContextService $workerContext,
    ) {}

    /**
     * Record a queued job
     *
     * Note: Caller (listener) is responsible for checking if monitoring is enabled.
     */
    public function execute(object $event): JobMonitor
    {
        // JobQueued event structure: connectionName, job (the actual job object, not Job interface)
        $jobInstance = $event->job ?? null;
        $connectionName = $event->connectionName ?? 'default';

        if ($jobInstance === null) {
            throw new \RuntimeException('Job instance not found in event');
        }

        $workerContext = $this->workerContext->capture();

        // Serialize the job to get payload
        $payload = $this->serializeJob($jobInstance);

        $data = new JobMonitorData(
            id: null,
            uuid: Str::uuid()->toString(),
            jobId: null, // Will be set when job starts processing
            jobClass: $this->getJobClass($jobInstance),
            displayName: $this->getDisplayName($jobInstance),
            connection: $connectionName,
            queue: $this->getQueue($jobInstance),
            payload: config('queue-monitor.storage.store_payload', true) ? $payload : null,
            status: JobStatus::QUEUED,
            attempt: 1,
            maxAttempts: $this->getMaxAttempts($jobInstance),
            retriedFromId: null,
            serverName: $workerContext->serverName,
            workerId: $workerContext->workerId,
            workerType: $workerContext->workerType->value,
            cpuTimeMs: null,
            memoryPeakMb: null,
            fileDescriptors: null,
            durationMs: null,
            exception: null,
            tags: $this->extractTags($jobInstance),
            queuedAt: now(),
            startedAt: null,
            completedAt: null,
            createdAt: now(),
            updatedAt: now(),
        );

        /** @var JobMonitor */
        return DB::transaction(fn (): JobMonitor => $this->repository->create($data));
    }

    /**
     * Serialize job to payload
     *
     * @return array<string, mixed>
     */
    private function serializeJob(object $jobInstance): array
    {
        // Queue job wrappers (RedisJob, DatabaseJob, etc.) already carry the payload
        if ($jobInstance instanceof QueueJob) {
            return $jobInstance->payload();
        }

        try {
            $serialized = serialize($jobInstance);
        } catch (\Throwable) {
            $serialized = null;
        }

        return [
            'displayName' => $this->getDisplayName($jobInstance),
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [
                'commandName' => $jobInstance::class,
                'command' => $serialized,
            ],
        ];
    }

    /**
     * Get queue name from job
     */
    private function getQueue(object $jobInstance): string
    {
        if ($jobInstance instanceof QueueJob) {
            return $jobInstance->getQueue();
        }

        if (property_exists($jobInstance, 'queue')) {
            return $jobInstance->queue ?? 'default';
        }

        return 'default';
    }

    /**
     * Get max attempts from job
     */
    private function getMaxAttempts(object $jobInstance): int
    {
        if ($jobInstance instanceof QueueJob) {
            return $jobInstance->maxTries() ?? 1;
        }

        if (property_exists($jobInstance, 'tries')) {
            return $jobInstance->tries ?? 1;
        }

        return 1;
    }

    /**
     * Get job class name
     */
    private function getJobClass(object $jobInstance): string
    {
        if ($jobInstance instanceof QueueJob) {
            return $jobInstance->resolveName();
        }

        return $jobInstance::class;
    }

    /**
     * Get display name from job
     */
    private function getDisplayName(object $jobInstance): ?string
    {
        if ($jobInstance instanceof QueueJob) {
            return $jobInstance->resolveName();
        }

        if (method_exists($jobInstance, 'displayName')) {
            return $jobInstance->displayName();
        }

        return null;
    }

    /**
     * Extract tags from job
     *
     * @return array<string>|null
     */
    private function extractTags(object $jobInstance): ?array
    {
        if (method_exists($jobInstance, 'tags')) {
            $tags = $jobInstance->tags();

            if (! is_array($tags)) {
                return null;
            }

            // Ensure all tags are strings
            /** @var array<string> */
            return array_filter(
                array_map(fn (mixed $tag): string => is_string($tag) ? $tag : (string) $tag, $tags),
                fn (string $tag): bool => $tag !== ''
            );
        }

        return null;
    }
}
