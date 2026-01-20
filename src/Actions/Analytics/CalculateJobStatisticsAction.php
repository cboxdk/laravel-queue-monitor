<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Analytics;

use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;

final readonly class CalculateJobStatisticsAction
{
    public function __construct(
        private StatisticsRepositoryContract $repository,
    ) {}

    /**
     * Calculate global job statistics
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        if (! config('queue-monitor.enabled', true)) {
            return [];
        }

        return $this->repository->getGlobalStatistics();
    }
}
