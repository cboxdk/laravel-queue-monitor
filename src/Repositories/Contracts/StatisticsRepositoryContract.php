<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Repositories\Contracts;

use Cbox\LaravelQueueMonitor\DataTransferObjects\FailurePatterns;
use Cbox\LaravelQueueMonitor\DataTransferObjects\GlobalStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobClassStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueHealthEntry;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\QueueTimeline;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ServerStatistics;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ThroughputBucket;

interface StatisticsRepositoryContract
{
    /**
     * Get global job statistics
     */
    public function getGlobalStatistics(): GlobalStatistics;

    /**
     * Get per-server statistics
     *
     * @return array<int, ServerStatistics>
     */
    public function getServerStatistics(?string $serverName = null): array;

    /**
     * Get per-queue statistics
     *
     * @return array<int, QueueStatistics>
     */
    public function getQueueStatistics(?string $queue = null): array;

    /**
     * Get per-job-class statistics
     *
     * @return array<int, JobClassStatistics>
     */
    public function getJobClassStatistics(?string $jobClass = null): array;

    /**
     * Get failure pattern analysis
     */
    public function getFailurePatterns(): FailurePatterns;

    /**
     * Get queue health metrics
     *
     * @return array<int, QueueHealthEntry>
     */
    public function getQueueHealth(): array;

    /**
     * Get jobs per minute for the last N minutes
     *
     * @return array<int, ThroughputBucket>
     */
    public function getThroughputByMinute(int $minutes = 60): array;

    /**
     * Compute per-minute throughput, optionally filtered to a single entity
     * (e.g. ['queue' => 'payments']). Uncached; used by drill-downs.
     *
     * @param  array<string, string>|null  $filter
     * @return array<int, ThroughputBucket>
     */
    public function computeThroughputByMinute(int $minutes = 60, ?array $filter = null): array;

    /**
     * Per-minute scaling timeline for one queue: job volume, duration, and
     * memory buckets plus live processing/waiting/delayed counts.
     */
    public function getQueueTimeline(string $queue, int $minutes = 60): QueueTimeline;
}
