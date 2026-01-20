<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
use Illuminate\Console\Command;

class LaravelQueueMonitorCommand extends Command
{
    public $signature = 'queue-monitor:stats
                        {--connection= : The connection to show stats for}
                        {--queue= : The queue to show stats for}';

    public $description = 'Show queue monitor statistics';

    public function handle(LaravelQueueMonitor $monitor): int
    {
        $this->info('Queue Monitor Statistics');
        $this->newLine();

        $stats = $monitor->statistics();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Jobs', number_format((int) ($stats['total'] ?? 0))],
                ['Completed', number_format((int) ($stats['completed'] ?? 0))],
                ['Failed', number_format((int) ($stats['failed'] ?? 0))],
                ['Processing', number_format((int) ($stats['processing'] ?? 0))],
                ['Success Rate', (string) ($stats['success_rate'] ?? 0).'%'],
                ['Failure Rate', (string) ($stats['failure_rate'] ?? 0).'%'],
                ['Avg Duration', isset($stats['avg_duration_ms']) && is_numeric($stats['avg_duration_ms']) ? number_format((float) $stats['avg_duration_ms']).'ms' : 'N/A'],
                ['Max Duration', isset($stats['max_duration_ms']) && is_numeric($stats['max_duration_ms']) ? number_format((int) $stats['max_duration_ms']).'ms' : 'N/A'],
                ['Avg Memory', isset($stats['avg_memory_mb']) && is_numeric($stats['avg_memory_mb']) ? number_format((float) $stats['avg_memory_mb'], 2).'MB' : 'N/A'],
                ['Max Memory', isset($stats['max_memory_mb']) && is_numeric($stats['max_memory_mb']) ? number_format((float) $stats['max_memory_mb'], 2).'MB' : 'N/A'],
            ]
        );

        return self::SUCCESS;
    }
}
