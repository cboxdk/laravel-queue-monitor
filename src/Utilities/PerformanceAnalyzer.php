<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Utilities;

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PerformanceAnalyzer
{
    /**
     * Get percentile statistics for job duration
     *
     * @return array{p50: float, p75: float, p90: float, p95: float, p99: float}
     */
    public static function getDurationPercentiles(string $jobClass): array
    {
        $durations = JobMonitor::where('job_class', $jobClass)
            ->whereNotNull('duration_ms')
            ->orderBy('duration_ms')
            ->pluck('duration_ms')
            ->values();

        if ($durations->isEmpty()) {
            return [
                'p50' => 0.0,
                'p75' => 0.0,
                'p90' => 0.0,
                'p95' => 0.0,
                'p99' => 0.0,
            ];
        }

        return [
            'p50' => self::percentile($durations, 50),
            'p75' => self::percentile($durations, 75),
            'p90' => self::percentile($durations, 90),
            'p95' => self::percentile($durations, 95),
            'p99' => self::percentile($durations, 99),
        ];
    }

    /**
     * Calculate percentile from collection
     *
     * @param  Collection<int, mixed>  $values
     */
    private static function percentile(Collection $values, float $percentile): float
    {
        $count = $values->count();

        if ($count === 0) {
            return 0.0;
        }

        $index = (int) ceil(($percentile / 100) * $count) - 1;
        $index = max(0, min($index, $count - 1));

        return (float) $values->get($index, 0.0);
    }

    /**
     * Identify performance regressions
     *
     * @return array{baseline: array, current: array, regression: bool, change_percent: float}
     */
    public static function detectRegression(
        string $jobClass,
        int $baselineDays = 30,
        int $comparisonDays = 7
    ): array {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        $baseline = DB::table($prefix.'jobs')
            ->where('job_class', $jobClass)
            ->whereBetween('completed_at', [
                now()->subDays($baselineDays),
                now()->subDays($comparisonDays),
            ])
            ->selectRaw('AVG(duration_ms) as avg_duration, AVG(memory_peak_mb) as avg_memory')
            ->first();

        $current = DB::table($prefix.'jobs')
            ->where('job_class', $jobClass)
            ->where('completed_at', '>=', now()->subDays($comparisonDays))
            ->selectRaw('AVG(duration_ms) as avg_duration, AVG(memory_peak_mb) as avg_memory')
            ->first();

        $baselineAvg = $baseline->avg_duration ?? 0;
        $currentAvg = $current->avg_duration ?? 0;

        $changePercent = $baselineAvg > 0
            ? (($currentAvg - $baselineAvg) / $baselineAvg) * 100
            : 0;

        return [
            'baseline' => [
                'avg_duration_ms' => round((float) $baselineAvg, 2),
                'avg_memory_mb' => round((float) ($baseline->avg_memory ?? 0), 2),
            ],
            'current' => [
                'avg_duration_ms' => round((float) $currentAvg, 2),
                'avg_memory_mb' => round((float) ($current->avg_memory ?? 0), 2),
            ],
            'regression' => $changePercent > 20, // >20% slower = regression
            'change_percent' => round($changePercent, 2),
        ];
    }

    /**
     * Get slowest jobs for a job class
     *
     * @return Collection<int, JobMonitor>
     */
    public static function getSlowestJobs(string $jobClass, int $limit = 10): Collection
    {
        return JobMonitor::where('job_class', $jobClass)
            ->whereNotNull('duration_ms')
            ->orderByDesc('duration_ms')
            ->limit($limit)
            ->get();
    }

    /**
     * Get jobs by duration buckets
     *
     * @return array<string, int>
     */
    public static function getDurationDistribution(string $jobClass): array
    {
        $jobs = JobMonitor::where('job_class', $jobClass)
            ->whereNotNull('duration_ms')
            ->pluck('duration_ms');

        return [
            '0-100ms' => $jobs->filter(fn ($d) => $d <= 100)->count(),
            '100-500ms' => $jobs->filter(fn ($d) => $d > 100 && $d <= 500)->count(),
            '500ms-1s' => $jobs->filter(fn ($d) => $d > 500 && $d <= 1000)->count(),
            '1s-5s' => $jobs->filter(fn ($d) => $d > 1000 && $d <= 5000)->count(),
            '5s-10s' => $jobs->filter(fn ($d) => $d > 5000 && $d <= 10000)->count(),
            '>10s' => $jobs->filter(fn ($d) => $d > 10000)->count(),
        ];
    }

    /**
     * Calculate throughput (jobs per second)
     */
    public static function calculateThroughput(string $queue, int $hours = 1): float
    {
        $jobs = JobMonitor::where('queue', $queue)
            ->where('queued_at', '>=', now()->subHours($hours))
            ->count();

        $seconds = $hours * 3600;

        return $jobs / $seconds;
    }

    /**
     * Get error rate trend
     *
     * @return array<string, array{date: string, error_rate: float}>
     */
    public static function getErrorRateTrend(int $days = 7): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');
        $trend = [];

        for ($i = 0; $i < $days; $i++) {
            $date = today()->subDays($i);

            $stats = DB::table($prefix.'jobs')
                ->whereDate('completed_at', $date)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ("failed", "timeout") THEN 1 ELSE 0 END) as failed
                ')
                ->first();

            $total = (int) ($stats->total ?? 0);
            $failed = (int) ($stats->failed ?? 0);

            $errorRate = $total > 0 ? ($failed / $total) * 100 : 0;

            $trend[$date->toDateString()] = [
                'date' => $date->toDateString(),
                'error_rate' => round($errorRate, 2),
                'total' => $total,
                'failed' => $failed,
            ];
        }

        return $trend;
    }
}
