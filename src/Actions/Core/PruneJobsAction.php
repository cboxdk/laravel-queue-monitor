<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class PruneJobsAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Prune old job records using both time-based and row-count limits.
     * Both strategies run when configured — whichever triggers first wins.
     *
     * @param  array<JobStatus>|null  $statuses
     */
    public function execute(?int $days = null, ?array $statuses = null, ?int $maxRows = null): int
    {
        if (! config('queue-monitor.enabled', true)) {
            return 0;
        }

        $pruneStatuses = $statuses ?? $this->getConfiguredPruneStatuses();
        $totalDeleted = 0;

        // Time-based pruning
        /** @var int $configDays */
        $configDays = config('queue-monitor.retention.days', 7);
        $retentionDays = $days ?? $configDays;
        $totalDeleted += $this->repository->prune($retentionDays, $pruneStatuses);

        // Row-count pruning (safety net for high-throughput systems)
        /** @var int|null $configMaxRows */
        $configMaxRows = config('queue-monitor.retention.max_rows');
        $effectiveMaxRows = $maxRows ?? $configMaxRows;

        if ($effectiveMaxRows !== null && $effectiveMaxRows > 0) {
            $totalDeleted += $this->repository->pruneByMaxRows($effectiveMaxRows, $pruneStatuses);
        }

        return $totalDeleted;
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
