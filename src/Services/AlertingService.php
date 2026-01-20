<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

final class AlertingService
{
    /**
     * Check for alert conditions
     *
     * @return array<string, array{severity: string, message: string, count: int}>
     */
    public function checkAlertConditions(): array
    {
        $alerts = [];

        // Check for stuck jobs
        $stuck = QueryBuilderHelper::stuck(30)->count();
        if ($stuck > 0) {
            $alerts['stuck_jobs'] = [
                'severity' => 'warning',
                'message' => "{$stuck} jobs stuck in processing for > 30 minutes",
                'count' => $stuck,
            ];
        }

        // Check error rate
        $recentTotal = QueryBuilderHelper::lastHours(1)->count();
        $recentFailed = QueryBuilderHelper::lastHours(1)->failed()->count();
        $errorRate = $recentTotal > 0 ? ($recentFailed / $recentTotal) * 100 : 0;

        if ($errorRate > 20) {
            $alerts['high_error_rate'] = [
                'severity' => 'critical',
                'message' => sprintf('Error rate %.2f%% exceeds threshold (20%%)', $errorRate),
                'count' => $recentFailed,
            ];
        } elseif ($errorRate > 10) {
            $alerts['elevated_error_rate'] = [
                'severity' => 'warning',
                'message' => sprintf('Error rate %.2f%% elevated (threshold: 10%%)', $errorRate),
                'count' => $recentFailed,
            ];
        }

        // Check for high backlog
        $queued = JobMonitor::where('status', 'queued')->count();
        if ($queued > 1000) {
            $alerts['high_backlog'] = [
                'severity' => 'warning',
                'message' => "{$queued} jobs queued (threshold: 1000)",
                'count' => $queued,
            ];
        }

        // Check for slow jobs
        $slowJobs = QueryBuilderHelper::slow(30000) // > 30 seconds
            ->whereDate('completed_at', today())
            ->count();

        if ($slowJobs > 10) {
            $alerts['slow_jobs'] = [
                'severity' => 'info',
                'message' => "{$slowJobs} jobs took > 30 seconds today",
                'count' => $slowJobs,
            ];
        }

        return $alerts;
    }

    /**
     * Get critical alerts only
     *
     * @return array<string, array{severity: string, message: string, count: int}>
     */
    public function getCriticalAlerts(): array
    {
        /** @var array<string, array{severity: string, message: string, count: int}> $alerts */
        $alerts = collect($this->checkAlertConditions())
            ->filter(fn ($alert) => $alert['severity'] === 'critical')
            ->all();

        return $alerts;
    }

    /**
     * Check if system requires immediate attention
     */
    public function requiresAttention(): bool
    {
        return ! empty($this->getCriticalAlerts());
    }
}
