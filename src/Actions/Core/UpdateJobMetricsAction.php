<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class UpdateJobMetricsAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Update job with additional metrics
     *
     * This action is reserved for future integration with enhanced metrics
     * from laravel-queue-metrics or other monitoring systems.
     *
     * @param  array<string, mixed>  $metricsData
     */
    public function execute(array $metricsData, string $jobId): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        $jobMonitor = $this->repository->findByJobId($jobId);

        if ($jobMonitor === null) {
            return;
        }

        // Future: Extract additional metrics from metricsData
        // For now, metrics are captured directly in RecordJobCompletedAction
    }
}
