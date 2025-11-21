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

        $retentionDays = $days ?? config('queue-monitor.retention.days', 30);
        $pruneStatuses = $statuses ?? config('queue-monitor.retention.prune_statuses', ['completed']);

        if (! is_int($retentionDays)) {
            $retentionDays = 30;
        }

        if (! is_array($pruneStatuses)) {
            $pruneStatuses = ['completed'];
        }

        return $this->repository->prune($retentionDays, $pruneStatuses);
    }
}
