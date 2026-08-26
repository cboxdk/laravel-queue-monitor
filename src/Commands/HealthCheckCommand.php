<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\DataTransferObjects\HealthCheckResult;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ReadinessResult;
use Cbox\LaravelQueueMonitor\Services\Contracts\AlertingServiceContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\HealthCheckServiceContract;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    public $signature = 'queue-monitor:health
                        {--alerts : Show only active alerts}
                        {--readiness : Check production readiness configuration}
                        {--json : Output as JSON}';

    public $description = 'Check queue monitor system health';

    public function handle(HealthCheckServiceContract $healthCheck, AlertingServiceContract $alerting): int
    {
        if ($this->option('alerts')) {
            return $this->showAlerts($alerting);
        }

        if ($this->option('readiness')) {
            $readiness = $healthCheck->readiness();
            // The exit code reflects the check outcome regardless of output
            // format — the --json path is exactly what CI/deploy gates script.
            $exit = $readiness->status === 'ready' ? self::SUCCESS : self::FAILURE;

            if ($this->option('json')) {
                $json = json_encode($readiness, JSON_PRETTY_PRINT);
                $this->line($json !== false ? $json : '{}');

                return $exit;
            }

            $this->displayReadinessStatus($readiness);

            return $exit;
        }

        $health = $healthCheck->check();
        $exit = $health->status === 'healthy' ? self::SUCCESS : self::FAILURE;

        if ($this->option('json')) {
            $json = json_encode($health, JSON_PRETTY_PRINT);
            $this->line($json !== false ? $json : '{}');

            return $exit;
        }

        $this->displayHealthStatus($health);

        return $exit;
    }

    /**
     * Display health status in table format
     */
    private function displayHealthStatus(HealthCheckResult $health): void
    {
        $statusIcon = $health->status === 'healthy' ? '✅' : '⚠️';

        $this->info("{$statusIcon} System Status: ".strtoupper($health->status));
        $this->newLine();

        $rows = [];

        foreach ($health->checks as $name => $check) {
            /** @var bool $isHealthy */
            $isHealthy = $check['healthy'];
            $icon = $isHealthy ? '✅' : '❌';
            /** @var string $message */
            $message = $check['message'];
            $rows[] = [
                $icon,
                ucwords(str_replace('_', ' ', $name)),
                $message,
            ];
        }

        $this->table(['Status', 'Check', 'Details'], $rows);
    }

    /**
     * Display readiness status in table format
     */
    private function displayReadinessStatus(ReadinessResult $readiness): void
    {
        $this->info('Production Readiness: '.strtoupper($readiness->status));
        $this->newLine();

        $rows = [];

        foreach ($readiness->checks as $name => $check) {
            /** @var bool $isHealthy */
            $isHealthy = $check['healthy'];
            /** @var string $message */
            $message = $check['message'];
            $rows[] = [
                $isHealthy ? 'OK' : 'ACTION',
                ucwords(str_replace('_', ' ', $name)),
                $message,
            ];
        }

        $this->table(['Status', 'Check', 'Details'], $rows);
    }

    /**
     * Show active alerts
     */
    private function showAlerts(AlertingServiceContract $alerting): int
    {
        $alerts = $alerting->checkAlertConditions();

        if (empty($alerts)) {
            $this->info('✅ No active alerts');

            return self::SUCCESS;
        }

        $this->warn('⚠️ Active Alerts:');
        $this->newLine();

        $rows = [];
        foreach ($alerts as $name => $alert) {
            $icon = match ($alert->severity) {
                'critical' => '🔴',
                'warning' => '🟡',
                'info' => '🔵',
                default => '⚪',
            };

            $rows[] = [
                $icon,
                ucwords(str_replace('_', ' ', $name)),
                $alert->severity,
                $alert->message,
            ];
        }

        $this->table(['', 'Alert', 'Severity', 'Message'], $rows);

        return $alerting->requiresAttention() ? self::FAILURE : self::SUCCESS;
    }
}
