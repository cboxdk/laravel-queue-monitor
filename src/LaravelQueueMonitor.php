<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor;

use Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateJobStatisticsAction;
use Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateQueueHealthAction;
use Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateServerStatisticsAction;
use Cbox\LaravelQueueMonitor\Actions\Core\CancelJobAction;
use Cbox\LaravelQueueMonitor\Actions\Core\PruneJobsAction;
use Cbox\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobReplayData;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Illuminate\Support\Collection;

final class LaravelQueueMonitor
{
    public function __construct(
        private readonly JobMonitorRepositoryContract $repository,
        private readonly ReplayJobAction $replayAction,
        private readonly CancelJobAction $cancelAction,
        private readonly PruneJobsAction $pruneAction,
        private readonly CalculateJobStatisticsAction $jobStatsAction,
        private readonly CalculateServerStatisticsAction $serverStatsAction,
        private readonly CalculateQueueHealthAction $queueHealthAction,
    ) {}

    /**
     * Get a job by UUID
     */
    public function getJob(string $uuid): ?JobMonitor
    {
        return $this->repository->findByUuid($uuid);
    }

    /**
     * Get jobs with filters
     *
     * @return Collection<int, JobMonitor>
     */
    public function getJobs(JobFilterData $filters): Collection
    {
        return $this->repository->query($filters);
    }

    /**
     * Replay a job
     */
    public function replay(string $uuid): JobReplayData
    {
        return $this->replayAction->execute($uuid);
    }

    /**
     * Cancel a job
     */
    public function cancel(string $uuid): bool
    {
        return $this->cancelAction->execute($uuid);
    }

    /**
     * Get retry chain for a job
     *
     * @return Collection<int, JobMonitor>
     */
    public function getRetryChain(string $uuid): Collection
    {
        return $this->repository->getRetryChain($uuid);
    }

    /**
     * Get global job statistics
     *
     * @return array<string, mixed>
     */
    public function statistics(): array
    {
        return $this->jobStatsAction->execute();
    }

    /**
     * Get per-server statistics
     *
     * @return array<string, mixed>
     */
    public function serverStatistics(?string $serverName = null): array
    {
        return $this->serverStatsAction->execute($serverName);
    }

    /**
     * Get queue health metrics
     *
     * @return array<string, mixed>
     */
    public function queueHealth(): array
    {
        return $this->queueHealthAction->execute();
    }

    /**
     * Prune old job records
     *
     * @param  array<string>|null  $statuses
     */
    public function prune(?int $days = null, ?array $statuses = null): int
    {
        return $this->pruneAction->execute($days, $statuses);
    }

    /**
     * Get failed jobs
     *
     * @return Collection<int, JobMonitor>
     */
    public function getFailedJobs(int $limit = 100): Collection
    {
        return $this->repository->getFailedJobs($limit);
    }

    /**
     * Get recent jobs
     *
     * @return Collection<int, JobMonitor>
     */
    public function getRecentJobs(int $limit = 100): Collection
    {
        return $this->repository->getRecentJobs($limit);
    }
}
