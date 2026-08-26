<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Cbox\LaravelQueueMonitor\DataTransferObjects\AlertEntry;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Services\Contracts\AlertingServiceContract;
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

final class AlertingService implements AlertingServiceContract
{
    /**
     * Check for alert conditions
     *
     * @return array<string, AlertEntry>
     */
    public function checkAlertConditions(): array
    {
        $alerts = [];

        // Thresholds are single-sourced from queue-monitor.health so the alerts
        // panel and the health checks agree on what "stuck", "high error rate",
        // and "high backlog" mean.
        $stuckMinutes = $this->intConfig('queue-monitor.health.stuck_job_minutes', 30);
        $errorWarn = $this->floatConfig('queue-monitor.health.error_rate_threshold', 10.0);
        $errorCritical = $this->floatConfig('queue-monitor.health.error_rate_critical_threshold', 20.0);
        $backlogThreshold = $this->intConfig('queue-monitor.health.queued_jobs_threshold', 1000);

        // Check for stuck jobs
        $stuck = QueryBuilderHelper::stuck($stuckMinutes)->count();
        if ($stuck > 0) {
            $alerts['stuck_jobs'] = new AlertEntry(
                severity: 'warning',
                message: "{$stuck} jobs stuck in processing for > {$stuckMinutes} minutes",
                count: $stuck,
            );
        }

        // Check error rate
        $recentTotal = QueryBuilderHelper::lastHours(1)->count();
        $recentFailed = QueryBuilderHelper::lastHours(1)->failed()->count();
        $errorRate = $recentTotal > 0 ? ($recentFailed / $recentTotal) * 100 : 0;

        if ($errorRate > $errorCritical) {
            $alerts['high_error_rate'] = new AlertEntry(
                severity: 'critical',
                message: sprintf('Error rate %.2f%% exceeds threshold (%s%%)', $errorRate, rtrim(rtrim(sprintf('%.1f', $errorCritical), '0'), '.')),
                count: $recentFailed,
            );
        } elseif ($errorRate > $errorWarn) {
            $alerts['elevated_error_rate'] = new AlertEntry(
                severity: 'warning',
                message: sprintf('Error rate %.2f%% elevated (threshold: %s%%)', $errorRate, rtrim(rtrim(sprintf('%.1f', $errorWarn), '0'), '.')),
                count: $recentFailed,
            );
        }

        // Check for high backlog
        $queued = JobMonitor::where('status', JobStatus::QUEUED)->count();
        if ($queued > $backlogThreshold) {
            $alerts['high_backlog'] = new AlertEntry(
                severity: 'warning',
                message: "{$queued} jobs queued (threshold: {$backlogThreshold})",
                count: $queued,
            );
        }

        // Check for slow jobs
        $slowJobs = QueryBuilderHelper::slow(30000) // > 30 seconds
            ->whereDate('completed_at', today())
            ->count();

        if ($slowJobs > 10) {
            $alerts['slow_jobs'] = new AlertEntry(
                severity: 'info',
                message: "{$slowJobs} jobs took > 30 seconds today",
                count: $slowJobs,
            );
        }

        return $alerts;
    }

    /**
     * Get critical alerts only
     *
     * @return array<string, AlertEntry>
     */
    public function getCriticalAlerts(): array
    {
        return collect($this->checkAlertConditions())
            ->filter(fn (AlertEntry $alert): bool => $alert->severity === 'critical')
            ->all();
    }

    /**
     * Check if system requires immediate attention
     */
    public function requiresAttention(): bool
    {
        return ! empty($this->getCriticalAlerts());
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function floatConfig(string $key, float $default): float
    {
        $value = config($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }
}
