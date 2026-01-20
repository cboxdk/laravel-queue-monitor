<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Repositories\Contracts;

interface StatisticsRepositoryContract
{
    /**
     * Get global job statistics
     *
     * @return array<string, mixed>
     */
    public function getGlobalStatistics(): array;

    /**
     * Get per-server statistics
     *
     * @return array<string, mixed>
     */
    public function getServerStatistics(?string $serverName = null): array;

    /**
     * Get per-queue statistics
     *
     * @return array<string, mixed>
     */
    public function getQueueStatistics(?string $queue = null): array;

    /**
     * Get per-job-class statistics
     *
     * @return array<string, mixed>
     */
    public function getJobClassStatistics(?string $jobClass = null): array;

    /**
     * Get failure pattern analysis
     *
     * @return array<string, mixed>
     */
    public function getFailurePatterns(): array;

    /**
     * Get queue health metrics
     *
     * @return array<string, mixed>
     */
    public function getQueueHealth(): array;
}
