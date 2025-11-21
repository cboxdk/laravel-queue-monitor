<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\DataTransferObjects;

use Carbon\Carbon;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;

final readonly class JobFilterData
{
    /**
     * @param  array<JobStatus>|null  $statuses
     * @param  array<string>|null  $queues
     * @param  array<string>|null  $jobClasses
     * @param  array<string>|null  $serverNames
     * @param  array<string>|null  $tags
     */
    public function __construct(
        public ?array $statuses = null,
        public ?array $queues = null,
        public ?string $connection = null,
        public ?array $jobClasses = null,
        public ?array $serverNames = null,
        public ?string $workerId = null,
        public ?string $workerType = null,
        public ?array $tags = null,
        public ?Carbon $queuedAfter = null,
        public ?Carbon $queuedBefore = null,
        public ?Carbon $startedAfter = null,
        public ?Carbon $startedBefore = null,
        public ?Carbon $completedAfter = null,
        public ?Carbon $completedBefore = null,
        public ?int $minDurationMs = null,
        public ?int $maxDurationMs = null,
        public ?string $search = null,
        public int $limit = 50,
        public int $offset = 0,
        public string $sortBy = 'queued_at',
        public string $sortDirection = 'desc',
    ) {}

    /**
     * Create from request array
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            statuses: isset($data['statuses']) && is_array($data['statuses'])
                ? array_map(
                    fn (mixed $status): JobStatus => JobStatus::from(is_string($status) ? $status : (string) $status),
                    $data['statuses']
                )
                : null,
            queues: isset($data['queues']) && is_array($data['queues'])
                ? $data['queues']
                : null,
            connection: isset($data['connection']) ? (string) $data['connection'] : null,
            jobClasses: isset($data['job_classes']) && is_array($data['job_classes'])
                ? $data['job_classes']
                : null,
            serverNames: isset($data['server_names']) && is_array($data['server_names'])
                ? $data['server_names']
                : null,
            workerId: isset($data['worker_id']) ? (string) $data['worker_id'] : null,
            workerType: isset($data['worker_type']) ? (string) $data['worker_type'] : null,
            tags: isset($data['tags']) && is_array($data['tags'])
                ? $data['tags']
                : null,
            queuedAfter: isset($data['queued_after']) ? Carbon::parse($data['queued_after']) : null,
            queuedBefore: isset($data['queued_before']) ? Carbon::parse($data['queued_before']) : null,
            startedAfter: isset($data['started_after']) ? Carbon::parse($data['started_after']) : null,
            startedBefore: isset($data['started_before']) ? Carbon::parse($data['started_before']) : null,
            completedAfter: isset($data['completed_after']) ? Carbon::parse($data['completed_after']) : null,
            completedBefore: isset($data['completed_before']) ? Carbon::parse($data['completed_before']) : null,
            minDurationMs: isset($data['min_duration_ms']) ? (int) $data['min_duration_ms'] : null,
            maxDurationMs: isset($data['max_duration_ms']) ? (int) $data['max_duration_ms'] : null,
            search: isset($data['search']) ? (string) $data['search'] : null,
            limit: isset($data['limit']) ? min((int) $data['limit'], 1000) : 50,
            offset: isset($data['offset']) ? (int) $data['offset'] : 0,
            sortBy: isset($data['sort_by']) ? (string) $data['sort_by'] : 'queued_at',
            sortDirection: isset($data['sort_direction']) &&
                in_array(strtolower((string) $data['sort_direction']), ['asc', 'desc'])
                ? strtolower((string) $data['sort_direction'])
                : 'desc',
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
            'statuses' => $this->statuses !== null
                ? array_map(fn (JobStatus $status) => $status->value, $this->statuses)
                : null,
            'queues' => $this->queues,
            'connection' => $this->connection,
            'job_classes' => $this->jobClasses,
            'server_names' => $this->serverNames,
            'worker_id' => $this->workerId,
            'worker_type' => $this->workerType,
            'tags' => $this->tags,
            'queued_after' => $this->queuedAfter?->toIso8601String(),
            'queued_before' => $this->queuedBefore?->toIso8601String(),
            'started_after' => $this->startedAfter?->toIso8601String(),
            'started_before' => $this->startedBefore?->toIso8601String(),
            'completed_after' => $this->completedAfter?->toIso8601String(),
            'completed_before' => $this->completedBefore?->toIso8601String(),
            'min_duration_ms' => $this->minDurationMs,
            'max_duration_ms' => $this->maxDurationMs,
            'search' => $this->search,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'sort_by' => $this->sortBy,
            'sort_direction' => $this->sortDirection,
        ];
    }

    /**
     * Check if any filters are applied
     */
    public function hasFilters(): bool
    {
        return $this->statuses !== null
            || $this->queues !== null
            || $this->connection !== null
            || $this->jobClasses !== null
            || $this->serverNames !== null
            || $this->workerId !== null
            || $this->workerType !== null
            || $this->tags !== null
            || $this->queuedAfter !== null
            || $this->queuedBefore !== null
            || $this->startedAfter !== null
            || $this->startedBefore !== null
            || $this->completedAfter !== null
            || $this->completedBefore !== null
            || $this->minDurationMs !== null
            || $this->maxDurationMs !== null
            || $this->search !== null;
    }
}
