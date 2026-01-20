<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\Services\AlertingService;
use Cbox\LaravelQueueMonitor\Services\HealthCheckService;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    public $signature = 'queue-monitor:health
                        {--alerts : Show only active alerts}
                        {--json : Output as JSON}';

    public $description = 'Check queue monitor system health';

    public function handle(HealthCheckService $healthCheck, AlertingService $alerting): int
    {
        if ($this->option('alerts')) {
            return $this->showAlerts($alerting);
        }

        $health = $healthCheck->check();

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->displayHealthStatus($health);

        return $health['status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Display health status in table format
     *
     * @param  array<string, mixed>  $health
     */
    private function displayHealthStatus(array $health): void
    {
        $statusIcon = $health['status'] === 'healthy' ? '✅' : '⚠️';

        $this->info("{$statusIcon} System Status: ".strtoupper($health['status']));
        $this->newLine();

        $rows = [];

        foreach ($health['checks'] as $name => $check) {
            $icon = $check['healthy'] ? '✅' : '❌';
            $rows[] = [
                $icon,
                ucwords(str_replace('_', ' ', $name)),
                $check['message'],
            ];
        }

        $this->table(['Status', 'Check', 'Details'], $rows);
    }

    /**
     * Show active alerts
     */
    private function showAlerts(AlertingService $alerting): int
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
            $icon = match ($alert['severity']) {
                'critical' => '🔴',
                'warning' => '🟡',
                'info' => '🔵',
                default => '⚪',
            };

            $rows[] = [
                $icon,
                ucwords(str_replace('_', ' ', $name)),
                $alert['severity'],
                $alert['message'],
            ];
        }

        $this->table(['', 'Alert', 'Severity', 'Message'], $rows);

        return $alerting->requiresAttention() ? self::FAILURE : self::SUCCESS;
    }
}
