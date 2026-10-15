<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\WorkerContextServiceContract;
use Cbox\LaravelQueueMonitor\Support\JobKey;
use Cbox\LaravelQueueMonitor\Support\QueueName;
use Cbox\LaravelQueueMonitor\Utilities\JobPayloadSerializer;
use Illuminate\Contracts\Queue\Job as QueueJob;
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

        if ($jobInstance === null) {
            throw new \RuntimeException('Job instance not found in event');
        }

        $jobId = $this->resolveJobId($event, $jobInstance);

        $queue = $this->getQueue($event, $jobInstance, $connectionName);
        $availableAt = $this->calculateAvailableAt($event->delay ?? null, $connectionName, $queue);

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
            queue: $queue,
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
        return JobMonitor::transaction(fn (): JobMonitor => $this->repository->create($data));
    }

    /**
     * Resolve the key the row is recorded under, following the JobKey rule
     *
     * When the event already carries a queue Job (a job recorded on first
     * processing because it was never seen being queued), JobKey derives the key
     * directly. On JobQueued, the driver's id is used when it has one; otherwise
     * the payload uuid is used, which is what JobKey falls back to when the worker
     * later reports an empty job id, so the queued row and the processing row
     * still match. Laravel's SyncQueue executes jobs inline without raising
     * JobQueued, so sync jobs take the first path.
     */
    private function resolveJobId(object $event, object $jobInstance): ?string
    {
        if ($jobInstance instanceof QueueJob) {
            return JobKey::of($jobInstance);
        }

        $eventJobId = $event->id ?? null;

        if ((is_string($eventJobId) || is_int($eventJobId)) && (string) $eventJobId !== '') {
            return (string) $eventJobId;
        }

        $payload = $event->payload ?? null;

        if (! is_string($payload) || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        $uuid = is_array($decoded) ? ($decoded['uuid'] ?? null) : null;

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
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
     *
     * On SQS the worker's job and the connection default are full queue URLs, so
     * the result is reduced to the queue name to match rows recorded at dispatch.
     */
    private function getQueue(object $event, object $jobInstance, string $connectionName): string
    {
        return QueueName::normalize($connectionName, $this->resolveRawQueue($event, $jobInstance, $connectionName));
    }

    private function resolveRawQueue(object $event, object $jobInstance, string $connectionName): string
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
     *
     * For a queue Job (sync jobs, and any job first seen when it starts
     * processing) the class is the payload's `data.commandName`: the command's
     * FQCN, written by Queue::createPayload() for every dispatched object and
     * readable without unserializing the command. resolveName() is NOT the
     * class — it returns the payload `displayName`, which is whatever the job's
     * displayName() method says (e.g. "Send invoice reminder"), and recording
     * that as job_class split one class into several in every per-class view.
     * resolveName() is only the fallback for payloads without a command, such
     * as a raw `Queue::push('Handler@method')` string job.
     */
    private function getJobClass(object $jobInstance): string
    {
        if ($jobInstance instanceof QueueJob) {
            $commandName = $jobInstance->payload()['data']['commandName'] ?? null;

            if (is_string($commandName) && $commandName !== '') {
                return $commandName;
            }

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
     *
     * SqsQueue::getQueueableOptions() drops DelaySeconds for FIFO queues, which
     * reject it, so a delayed job pushed to one is available immediately.
     */
    private function calculateAvailableAt(mixed $delay, string $connectionName, string $queue): ?CarbonInterface
    {
        if ($delay === null || $delay === 0) {
            return null; // Not delayed — available immediately
        }

        if (QueueName::isSqsFifo($connectionName, $queue)) {
            return null;
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
