<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Core;

use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class PruneJobsAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Prune old job records
     *
     * @param  array<JobStatus|string>|null  $statuses
     */
    public function execute(?int $days = null, ?array $statuses = null): int
    {
        if (! config('queue-monitor.enabled', true)) {
            return 0;
        }

        /** @var int $configDays */
        $configDays = config('queue-monitor.retention.days', 30);
        $retentionDays = $days ?? $configDays;
        $pruneStatuses = $statuses ?? $this->getConfiguredPruneStatuses();

        return $this->repository->prune($retentionDays, $pruneStatuses);
    }

    /**
     * Get configured prune statuses
     *
     * @return array<JobStatus>
     */
    private function getConfiguredPruneStatuses(): array
    {
        /** @var array<string> $configStatuses */
        $configStatuses = config('queue-monitor.retention.prune_statuses', ['completed', 'failed']);

        return array_map(
            fn (string $status): JobStatus => JobStatus::from($status),
            $configStatuses
        );
    }
}
