<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
use Illuminate\Console\Command;

class LaravelQueueMonitorCommand extends Command
{
    public $signature = 'queue-monitor:stats
                        {--connection= : The connection to show stats for}
                        {--queue= : The queue to show stats for}
                        {--json : Output as JSON}';

    public $description = 'Show queue monitor statistics';

    public function handle(LaravelQueueMonitor $monitor): int
    {
        $stats = $monitor->statistics();

        if ($this->option('json')) {
            $json = json_encode($stats, JSON_PRETTY_PRINT);
            $this->line($json !== false ? $json : '{}');

            return self::SUCCESS;
        }

        $this->info('Queue Monitor Statistics');
        $this->newLine();

        $total = is_numeric($stats['total'] ?? null) ? (int) $stats['total'] : 0;
        $completed = is_numeric($stats['completed'] ?? null) ? (int) $stats['completed'] : 0;
        $failed = is_numeric($stats['failed'] ?? null) ? (int) $stats['failed'] : 0;
        $processing = is_numeric($stats['processing'] ?? null) ? (int) $stats['processing'] : 0;
        $successRate = is_numeric($stats['success_rate'] ?? null) ? (string) $stats['success_rate'] : '0';
        $failureRate = is_numeric($stats['failure_rate'] ?? null) ? (string) $stats['failure_rate'] : '0';

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Jobs', number_format($total)],
                ['Completed', number_format($completed)],
                ['Failed', number_format($failed)],
                ['Processing', number_format($processing)],
                ['Success Rate', $successRate.'%'],
                ['Failure Rate', $failureRate.'%'],
                ['Avg Duration', isset($stats['avg_duration_ms']) && is_numeric($stats['avg_duration_ms']) ? number_format((float) $stats['avg_duration_ms']).'ms' : 'N/A'],
                ['Max Duration', isset($stats['max_duration_ms']) && is_numeric($stats['max_duration_ms']) ? number_format((int) $stats['max_duration_ms']).'ms' : 'N/A'],
                ['Avg Memory', isset($stats['avg_memory_mb']) && is_numeric($stats['avg_memory_mb']) ? number_format((float) $stats['avg_memory_mb'], 2).'MB' : 'N/A'],
                ['Max Memory', isset($stats['max_memory_mb']) && is_numeric($stats['max_memory_mb']) ? number_format((float) $stats['max_memory_mb'], 2).'MB' : 'N/A'],
            ]
        );

        return self::SUCCESS;
    }
}
