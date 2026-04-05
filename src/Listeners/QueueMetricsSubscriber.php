<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMetrics\Events\JobMetricsCompleted;
use Cbox\LaravelQueueMetrics\Events\JobMetricsFailed;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Events\Dispatcher;

/**
 * Captures per-job CPU time and memory (RSS) from laravel-queue-metrics (^2.3).
 *
 * This is the ONLY source of CPU/memory data — it comes from ProcessMetrics
 * (cboxdk/system-metrics) with per-process instrumentation, not getrusage().
 */
final class QueueMetricsSubscriber
{
    public function handleJobMetricsCompleted(JobMetricsCompleted $event): void
    {
        $this->updateJobMetrics($event->jobId, $event->cpuTimeMs, $event->memoryMb);
    }

    public function handleJobMetricsFailed(JobMetricsFailed $event): void
    {
        $this->updateJobMetrics($event->jobId, $event->cpuTimeMs, $event->memoryMb);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(JobMetricsCompleted::class, [self::class, 'handleJobMetricsCompleted']);
        $events->listen(JobMetricsFailed::class, [self::class, 'handleJobMetricsFailed']);
    }

    private function updateJobMetrics(string $jobId, float $cpuTimeMs, float $memoryMb): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        try {
            $monitor = JobMonitor::where('job_id', $jobId)
                ->orderByDesc('attempt')
                ->orderByDesc('created_at')
                ->first();

            if ($monitor === null) {
                return;
            }

            $monitor->update([
                'cpu_time_ms' => round($cpuTimeMs, 2),
                'memory_peak_mb' => round($memoryMb, 2),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
