<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Analytics;

use PHPeek\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;

final readonly class CalculateQueueHealthAction
{
    public function __construct(
        private StatisticsRepositoryContract $repository,
    ) {}

    /**
     * Calculate queue health metrics
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        if (! config('queue-monitor.enabled', true)) {
            return [];
        }

        return $this->repository->getQueueHealth();
    }
}
