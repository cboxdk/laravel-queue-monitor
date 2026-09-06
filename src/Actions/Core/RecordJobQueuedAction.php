<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\WorkerContextServiceContract;
use Cbox\LaravelQueueMonitor\Utilities\JobPayloadSerializer;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class RecordJobQueuedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private WorkerContextServiceContract $workerContext,
    ) {}

    /**
     * Record a queued job
     *
     * Note: Caller (listener) is responsible for checking if monitoring is enabled.
     */
    public function execute(object $event): ?JobMonitor
    {
        // JobQueued event has: connectionName, queue, id, job, payload, delay
        $jobInstance = $event->job ?? null;
        $connectionName = $event->connectionName ?? 'default';
        $jobId = isset($event->id) ? (string) $event->id : null;
        $availableAt = $this->calculateAvailableAt($event->delay ?? null);

        if ($jobInstance === null) {
            throw new \RuntimeException('Job instance not found in event');
        }

        $resolvedJob = $this->resolveMonitorableJob($jobInstance) ?? $jobInstance;

        if (! $this->shouldMonitor($resolvedJob)) {
            return null;
        }

        $workerContext = $this->workerContext->capture();

        $payload = null;

        if ($this->shouldStorePayload($resolvedJob)) {
            $serializedPayload = $this->serializeJob($jobInstance);
            $payload = JobPayloadSerializer::exceedsSizeLimit($serializedPayload) ? null : $serializedPayload;
        }

        $data = new JobMonitorData(
            id: null,
            uuid: Str::uuid()->toString(),
            jobId: $jobId,
            jobClass: $this->getJobClass($jobInstance),
            displayName: $this->getDisplayName($jobInstance),
            connection: $connectionName,
            queue: $this->getQueue($event, $jobInstance, $connectionName),
            payload: $payload,
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
            tags: $this->extractTags($resolvedJob),
            queuedAt: now(),
            availableAt: $availableAt,
            startedAt: null,
            completedAt: null,
            createdAt: now(),
            updatedAt: now(),
        );

        /** @var JobMonitor */
        return DB::transaction(fn (): JobMonitor => $this->repository->create($data));
    }

    private function shouldMonitor(object $jobInstance): bool
    {
        if (method_exists($jobInstance, 'shouldBeMonitored')) {
            return (bool) $jobInstance->shouldBeMonitored();
        }

        return true;
    }

    private function shouldStorePayload(object $jobInstance): bool
    {
        if (method_exists($jobInstance, 'shouldStorePayload')) {
            return (bool) $jobInstance->shouldStorePayload();
        }

        return (bool) config('queue-monitor.storage.store_payload', app()->environment('local'));
    }

    private function resolveMonitorableJob(object $jobInstance): ?object
    {
        if (! $jobInstance instanceof QueueJob) {
            return $jobInstance;
        }

        $payload = $jobInstance->payload();
        $command = $payload['data']['command'] ?? null;
        $commandName = $payload['data']['commandName'] ?? null;

        if (! is_string($command) || $command === '' || ! is_string($commandName) || $commandName === '') {
            return null;
        }

        try {
            // Restrict deserialization to the declared command class — the
            // serialized blob comes from the queue store, so an unrestricted
            // unserialize() would be an object-injection entry point.
            $resolved = unserialize($command, ['allowed_classes' => [$commandName]]);
        } catch (\Throwable) {
            return null;
        }

        return $resolved instanceof $commandName ? $resolved : null;
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
        } catch (\Throwable $e) {
            report($e);
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
     * Get queue name for the job.
     *
     * Prefer the queue carried by the JobQueued event: for jobs dispatched inside
     * a Bus::batch(...)->onQueue(...), the queue is applied at the bulk-push level
     * and the job instance's $queue property stays null, so reading it alone
     * mis-records every batched job as `default`. Fall back to the instance, then
     * to the connection's configured default queue name.
     */
    private function getQueue(object $event, object $jobInstance, string $connectionName): string
    {
        $eventQueue = $event->queue ?? null;

        if (is_string($eventQueue) && $eventQueue !== '') {
            return $eventQueue;
        }

        if ($jobInstance instanceof QueueJob) {
            return $jobInstance->getQueue();
        }

        if (property_exists($jobInstance, 'queue') && $jobInstance->queue !== null) {
            return $jobInstance->queue;
        }

        $configuredDefault = config("queue.connections.{$connectionName}.queue", 'default');

        return is_string($configuredDefault) ? $configuredDefault : 'default';
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
            $name = $jobInstance->resolveName();

            // resolveName() returns the handler class (CallQueuedHandler@call)
            // when displayName is null — fall back to data.commandName from payload
            if (str_contains($name, 'CallQueuedHandler')) {
                $payload = $jobInstance->payload();
                $commandName = $payload['data']['commandName'] ?? null;

                if (is_string($commandName) && $commandName !== '') {
                    return $commandName;
                }
            }

            return $name;
        }

        return $jobInstance::class;
    }

    /**
     * Get display name from job
     */
    private function getDisplayName(object $jobInstance): ?string
    {
        if ($jobInstance instanceof QueueJob) {
            $name = $jobInstance->resolveName();

            if (str_contains($name, 'CallQueuedHandler')) {
                $payload = $jobInstance->payload();

                return $payload['data']['commandName'] ?? null;
            }

            return $name;
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
                array_map(fn (mixed $tag): string => is_string($tag) ? $tag : (is_scalar($tag) || $tag === null ? (string) $tag : ''), $tags),
                fn (string $tag): bool => $tag !== ''
            );
        }

        return null;
    }

    /**
     * Calculate when a delayed job becomes available for processing
     */
    private function calculateAvailableAt(mixed $delay): ?Carbon
    {
        if ($delay === null || $delay === 0) {
            return null; // Not delayed — available immediately
        }

        if ($delay instanceof \DateTimeInterface) {
            return Carbon::instance($delay);
        }

        if ($delay instanceof \DateInterval) {
            return now()->add($delay);
        }

        if (is_numeric($delay)) {
            return now()->addSeconds((int) $delay);
        }

        return null;
    }
}
