<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Services;

use Illuminate\Support\Facades\DB;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;
use PHPeek\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

final class HealthCheckService
{
    /**
     * Perform comprehensive health check
     *
     * @return array{status: string, checks: array<string, array<string, mixed>>}
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'recent_activity' => $this->checkRecentActivity(),
            'stuck_jobs' => $this->checkStuckJobs(),
            'error_rate' => $this->checkErrorRate(),
            'queue_backlog' => $this->checkQueueBacklog(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['healthy']);

        return [
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Check database connectivity
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkDatabase(): array
    {
        try {
            /** @var string|null $connection */
            $connection = config('queue-monitor.database.connection');
            DB::connection($connection)->getPdo();

            $count = JobMonitor::count();

            return [
                'healthy' => true,
                'message' => 'Database connection successful',
                'details' => [
                    'total_jobs' => $count,
                    'connection' => $connection ?? 'default',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'Database connection failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Check for recent job activity
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkRecentActivity(): array
    {
        $recentJobs = QueryBuilderHelper::lastHours(1)->count();

        $healthy = $recentJobs > 0;

        return [
            'healthy' => $healthy,
            'message' => $healthy
                ? "Jobs processed in last hour: {$recentJobs}"
                : 'No jobs processed in last hour',
            'details' => [
                'jobs_last_hour' => $recentJobs,
            ],
        ];
    }

    /**
     * Check for stuck jobs
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkStuckJobs(): array
    {
        $stuck = QueryBuilderHelper::stuck(30)->count();

        $healthy = $stuck === 0;

        return [
            'healthy' => $healthy,
            'message' => $stuck > 0
                ? "{$stuck} jobs stuck in processing"
                : 'No stuck jobs detected',
            'details' => [
                'stuck_count' => $stuck,
                'threshold_minutes' => 30,
            ],
        ];
    }

    /**
     * Check error rate
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkErrorRate(): array
    {
        $recentTotal = QueryBuilderHelper::lastHours(1)->count();
        $recentFailed = QueryBuilderHelper::lastHours(1)
            ->whereIn('status', [JobStatus::FAILED->value, JobStatus::TIMEOUT->value])
            ->count();

        $errorRate = $recentTotal > 0 ? ($recentFailed / $recentTotal) * 100 : 0;
        $healthy = $errorRate < 10; // < 10% error rate

        return [
            'healthy' => $healthy,
            'message' => sprintf('Error rate: %.2f%%', $errorRate),
            'details' => [
                'error_rate' => round($errorRate, 2),
                'total_jobs' => $recentTotal,
                'failed_jobs' => $recentFailed,
                'threshold' => 10.0,
            ],
        ];
    }

    /**
     * Check queue backlog
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkQueueBacklog(): array
    {
        $processing = JobMonitor::where('status', JobStatus::PROCESSING)->count();
        $queued = JobMonitor::where('status', JobStatus::QUEUED)->count();

        $healthy = $queued < 1000 && $processing < 100;

        return [
            'healthy' => $healthy,
            'message' => "Queued: {$queued}, Processing: {$processing}",
            'details' => [
                'queued' => $queued,
                'processing' => $processing,
                'total_pending' => $queued + $processing,
            ],
        ];
    }

    /**
     * Check storage status
     *
     * @return array{healthy: bool, message: string, details?: array<string, mixed>}
     */
    private function checkStorage(): array
    {
        $prefix = config('queue-monitor.database.table_prefix', 'queue_monitor_');

        try {
            $tableSize = DB::select(
                'SELECT
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb,
                    TABLE_ROWS as row_count
                FROM information_schema.TABLES
                WHERE TABLE_NAME = ?
                AND TABLE_SCHEMA = DATABASE()',
                [$prefix.'jobs']
            );

            if (empty($tableSize)) {
                return [
                    'healthy' => true,
                    'message' => 'Storage check not available (non-MySQL database)',
                ];
            }

            $sizeMb = $tableSize[0]->size_mb ?? 0;
            $rows = $tableSize[0]->row_count ?? 0;

            $healthy = $sizeMb < 1000; // < 1GB

            return [
                'healthy' => $healthy,
                'message' => "{$sizeMb}MB, {$rows} rows",
                'details' => [
                    'size_mb' => $sizeMb,
                    'row_count' => $rows,
                    'threshold_mb' => 1000,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => true,
                'message' => 'Storage check not available',
            ];
        }
    }

    /**
     * Get overall health score (0-100)
     */
    public function getHealthScore(): int
    {
        $check = $this->check();
        $checks = $check['checks'];

        $healthyCount = collect($checks)->filter(fn ($c) => $c['healthy'])->count();
        $totalChecks = count($checks);

        return (int) round(($healthyCount / $totalChecks) * 100);
    }

    /**
     * Check if system is healthy
     */
    public function isHealthy(): bool
    {
        return $this->check()['status'] === 'healthy';
    }
}
