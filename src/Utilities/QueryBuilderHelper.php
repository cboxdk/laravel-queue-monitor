<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Utilities;

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Database\Eloquent\Builder;

final class QueryBuilderHelper
{
    /**
     * Get jobs from last N hours
     *
     * @return Builder<JobMonitor>
     */
    public static function lastHours(int $hours = 24): Builder
    {
        return JobMonitor::query()
            ->where('queued_at', '>=', now()->subHours($hours));
    }

    /**
     * Get jobs from today
     *
     * @return Builder<JobMonitor>
     */
    public static function today(): Builder
    {
        return JobMonitor::query()
            ->whereDate('queued_at', today());
    }

    /**
     * Get slow jobs (above threshold)
     *
     * @return Builder<JobMonitor>
     */
    public static function slow(int $thresholdMs = 5000): Builder
    {
        return JobMonitor::query()
            ->where('duration_ms', '>', $thresholdMs)
            ->whereNotNull('duration_ms');
    }

    /**
     * Get memory-intensive jobs
     *
     * @return Builder<JobMonitor>
     */
    public static function memoryIntensive(float $thresholdMb = 100.0): Builder
    {
        return JobMonitor::query()
            ->where('memory_peak_mb', '>', $thresholdMb)
            ->whereNotNull('memory_peak_mb');
    }

    /**
     * Get jobs that failed with specific exception
     *
     * @return Builder<JobMonitor>
     */
    public static function failedWith(string $exceptionClass): Builder
    {
        return JobMonitor::query()
            ->where('exception_class', $exceptionClass)
            ->where('status', JobStatus::FAILED);
    }

    /**
     * Get retried jobs (have parent)
     *
     * @return Builder<JobMonitor>
     */
    public static function retried(): Builder
    {
        return JobMonitor::query()
            ->whereNotNull('retried_from_id');
    }

    /**
     * Get jobs with retries (have children)
     *
     * @return Builder<JobMonitor>
     */
    public static function withRetries(): Builder
    {
        return JobMonitor::query()
            ->whereHas('retries');
    }

    /**
     * Get jobs processed by specific server
     *
     * @return Builder<JobMonitor>
     */
    public static function byServer(string $serverName): Builder
    {
        return JobMonitor::query()
            ->where('server_name', $serverName);
    }

    /**
     * Get horizon jobs only
     *
     * @return Builder<JobMonitor>
     */
    public static function horizonOnly(): Builder
    {
        return JobMonitor::query()
            ->where('worker_type', WorkerType::HORIZON);
    }

    /**
     * Get queue:work jobs only
     *
     * @return Builder<JobMonitor>
     */
    public static function queueWorkOnly(): Builder
    {
        return JobMonitor::query()
            ->where('worker_type', WorkerType::QUEUE_WORK);
    }

    /**
     * Get jobs between date range
     *
     * @return Builder<JobMonitor>
     */
    public static function between(Carbon $start, Carbon $end): Builder
    {
        return JobMonitor::query()
            ->whereBetween('queued_at', [$start, $end]);
    }

    /**
     * Get recently completed jobs
     *
     * @return Builder<JobMonitor>
     */
    public static function recentlyCompleted(int $limit = 100): Builder
    {
        return JobMonitor::query()
            ->where('status', JobStatus::COMPLETED)
            ->orderByDesc('completed_at')
            ->limit($limit);
    }

    /**
     * Get jobs with specific tag
     *
     * @return Builder<JobMonitor>
     */
    public static function withTag(string $tag): Builder
    {
        return JobMonitor::query()
            ->whereJsonContains('tags', $tag);
    }

    /**
     * Get jobs matching multiple tags (AND logic)
     *
     * @param  array<string>  $tags
     * @return Builder<JobMonitor>
     */
    public static function withAllTags(array $tags): Builder
    {
        $query = JobMonitor::query();

        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }

        return $query;
    }

    /**
     * Get jobs matching any of the tags (OR logic)
     *
     * @param  array<string>  $tags
     * @return Builder<JobMonitor>
     */
    public static function withAnyTag(array $tags): Builder
    {
        return JobMonitor::query()
            ->where(function ($query) use ($tags) {
                foreach ($tags as $tag) {
                    $query->orWhereJsonContains('tags', $tag);
                }
            });
    }

    /**
     * Get long-running jobs (still processing for X minutes)
     *
     * @return Builder<JobMonitor>
     */
    public static function longRunning(int $minutes = 10): Builder
    {
        return JobMonitor::query()
            ->where('status', JobStatus::PROCESSING)
            ->where('started_at', '<', now()->subMinutes($minutes));
    }

    /**
     * Get stuck jobs (processing but no recent activity)
     *
     * @return Builder<JobMonitor>
     */
    public static function stuck(int $minutes = 30): Builder
    {
        return self::longRunning($minutes);
    }
}
