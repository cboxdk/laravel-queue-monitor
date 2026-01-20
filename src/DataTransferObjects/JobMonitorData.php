<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\DataTransferObjects;

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

final readonly class JobMonitorData
{
    /**
     * @param  array<string>|null  $tags
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public ?int $id,
        public string $uuid,
        public ?string $jobId,
        public string $jobClass,
        public ?string $displayName,
        public string $connection,
        public string $queue,
        public ?array $payload,
        public JobStatus $status,
        public int $attempt,
        public int $maxAttempts,
        public ?int $retriedFromId,
        public string $serverName,
        public string $workerId,
        public string $workerType,
        public ?float $cpuTimeMs,
        public ?float $memoryPeakMb,
        public ?int $fileDescriptors,
        public ?int $durationMs,
        public ?ExceptionData $exception,
        public ?array $tags,
        public Carbon $queuedAt,
        public ?Carbon $startedAt,
        public ?Carbon $completedAt,
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {}

    /**
     * Create from array
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $uuid = $data['uuid'] ?? '';
        $jobClass = $data['job_class'] ?? '';
        $connection = $data['connection'] ?? '';
        $queue = $data['queue'] ?? '';
        $workerType = $data['worker_type'] ?? 'queue_work';

        return new self(
            id: isset($data['id']) && is_int($data['id']) ? $data['id'] : null,
            uuid: is_string($uuid) ? $uuid : (string) $uuid,
            jobId: isset($data['job_id']) && is_string($data['job_id']) ? $data['job_id'] : null,
            jobClass: is_string($jobClass) ? $jobClass : (string) $jobClass,
            displayName: isset($data['display_name']) && is_string($data['display_name']) ? $data['display_name'] : null,
            connection: is_string($connection) ? $connection : (string) $connection,
            queue: is_string($queue) ? $queue : (string) $queue,
            payload: isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : null,
            status: JobStatus::from(is_string($data['status'] ?? 'queued') ? $data['status'] : 'queued'),
            attempt: isset($data['attempt']) && is_int($data['attempt']) ? $data['attempt'] : 1,
            maxAttempts: isset($data['max_attempts']) && is_int($data['max_attempts']) ? $data['max_attempts'] : 1,
            retriedFromId: isset($data['retried_from_id']) && is_int($data['retried_from_id']) ? $data['retried_from_id'] : null,
            serverName: isset($data['server_name']) && is_string($data['server_name']) ? $data['server_name'] : 'unknown',
            workerId: isset($data['worker_id']) && is_string($data['worker_id']) ? $data['worker_id'] : 'unknown',
            workerType: is_string($workerType) ? $workerType : (string) $workerType,
            cpuTimeMs: isset($data['cpu_time_ms']) ? (float) $data['cpu_time_ms'] : null,
            memoryPeakMb: isset($data['memory_peak_mb']) ? (float) $data['memory_peak_mb'] : null,
            fileDescriptors: isset($data['file_descriptors']) ? (int) $data['file_descriptors'] : null,
            durationMs: isset($data['duration_ms']) ? (int) $data['duration_ms'] : null,
            exception: isset($data['exception']) && is_array($data['exception'])
                ? ExceptionData::fromArray($data['exception'])
                : null,
            tags: isset($data['tags']) && is_array($data['tags']) ? $data['tags'] : null,
            queuedAt: Carbon::parse($data['queued_at']),
            startedAt: isset($data['started_at']) ? Carbon::parse($data['started_at']) : null,
            completedAt: isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at']),
        );
    }

    /**
     * Convert to array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'job_id' => $this->jobId,
            'job_class' => $this->jobClass,
            'display_name' => $this->displayName,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'payload' => $this->payload,
            'status' => $this->status->value,
            'attempt' => $this->attempt,
            'max_attempts' => $this->maxAttempts,
            'retried_from_id' => $this->retriedFromId,
            'server_name' => $this->serverName,
            'worker_id' => $this->workerId,
            'worker_type' => $this->workerType,
            'cpu_time_ms' => $this->cpuTimeMs,
            'memory_peak_mb' => $this->memoryPeakMb,
            'file_descriptors' => $this->fileDescriptors,
            'duration_ms' => $this->durationMs,
            'exception' => $this->exception?->toArray(),
            'tags' => $this->tags,
            'queued_at' => $this->queuedAt->toIso8601String(),
            'started_at' => $this->startedAt?->toIso8601String(),
            'completed_at' => $this->completedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }

    /**
     * Check if job is finished
     */
    public function isFinished(): bool
    {
        return $this->status->isFinished();
    }

    /**
     * Check if job was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    /**
     * Check if job failed
     */
    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    /**
     * Check if job is retryable (failed and has attempts remaining)
     */
    public function isRetryable(): bool
    {
        return $this->isFailed() && $this->attempt < $this->maxAttempts;
    }

    /**
     * Check if this job is a retry of another job
     */
    public function isRetry(): bool
    {
        return $this->retriedFromId !== null;
    }

    /**
     * Get duration in seconds
     */
    public function durationInSeconds(): ?float
    {
        if ($this->durationMs === null) {
            return null;
        }

        return $this->durationMs / 1000;
    }

    /**
     * Calculate throughput (items/second) if available
     */
    public function throughput(): ?float
    {
        $duration = $this->durationInSeconds();

        if ($duration === null || $duration === 0.0) {
            return null;
        }

        // Assuming single item per job by default
        return 1.0 / $duration;
    }
}
