<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Analytics;

use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;

final readonly class CalculateServerStatisticsAction
{
    public function __construct(
        private StatisticsRepositoryContract $repository,
    ) {}

    /**
     * Calculate per-server statistics
     *
     * @return array<string, mixed>
     */
    public function execute(?string $serverName = null): array
    {
        if (! config('queue-monitor.enabled', true)) {
            return [];
        }

        return $this->repository->getServerStatistics($serverName);
    }
}
