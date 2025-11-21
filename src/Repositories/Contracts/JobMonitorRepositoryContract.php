<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Repositories\Contracts;

use Illuminate\Support\Collection;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobMonitorData;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

interface JobMonitorRepositoryContract
{
    /**
     * Create a new job monitor record
     */
    public function create(JobMonitorData $data): JobMonitor;

    /**
     * Update a job monitor record by UUID
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $data): JobMonitor;

    /**
     * Find a job monitor record by UUID
     */
    public function findByUuid(string $uuid): ?JobMonitor;

    /**
     * Find a job monitor record by job ID
     */
    public function findByJobId(string $jobId): ?JobMonitor;

    /**
     * Query job monitor records with filters
     *
     * @return Collection<int, JobMonitor>
     */
    public function query(JobFilterData $filters): Collection;

    /**
     * Get total count for query
     */
    public function count(JobFilterData $filters): int;

    /**
     * Get all retry attempts for a job
     *
     * @return Collection<int, JobMonitor>
     */
    public function getRetryChain(string $uuid): Collection;

    /**
     * Prune old job records
     */
    public function prune(int $days, array $statuses = []): int;

    /**
     * Delete a job monitor record
     */
    public function delete(string $uuid): bool;

    /**
     * Get jobs for a specific queue
     *
     * @return Collection<int, JobMonitor>
     */
    public function getByQueue(string $queue, ?string $connection = null, int $limit = 100): Collection;

    /**
     * Get jobs for a specific server
     *
     * @return Collection<int, JobMonitor>
     */
    public function getByServer(string $serverName, int $limit = 100): Collection;

    /**
     * Get failed jobs
     *
     * @return Collection<int, JobMonitor>
     */
    public function getFailedJobs(int $limit = 100): Collection;

    /**
     * Get recent jobs
     *
     * @return Collection<int, JobMonitor>
     */
    public function getRecentJobs(int $limit = 100): Collection;
}
