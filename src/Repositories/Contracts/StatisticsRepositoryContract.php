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
     * @return array<int, array<string, mixed>>
     */
    public function getServerStatistics(?string $serverName = null): array;

    /**
     * Get per-queue statistics
     *
     * @return array<int, array<string, mixed>>
     */
    public function getQueueStatistics(?string $queue = null): array;

    /**
     * Get per-job-class statistics
     *
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
     */
    public function getQueueHealth(): array;

    /**
     * Get jobs per minute for the last N minutes
     *
     * @return array<int, array{minute: string, total: int, completed: int, failed: int}>
     */
    public function getThroughputByMinute(int $minutes = 60): array;

    /**
     * Per-minute scaling timeline for one queue: job volume, duration, and
     * memory buckets plus live processing/waiting/delayed counts.
     *
     * @return array{
     *     buckets: array<int, array{minute: string, ts: string, total: int, completed: int, failed: int, avg_duration_ms: float|null, max_duration_ms: int|null, avg_memory_mb: float|null, max_memory_mb: float|null}>,
     *     live: array{processing: int, waiting: int, delayed: int},
     *     memory_limit_mb: float|null
     * }
     */
    public function getQueueTimeline(string $queue, int $minutes = 60): array;
}
