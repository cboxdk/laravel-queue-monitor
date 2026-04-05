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
            // Find the attempt that is currently processing (the one this event is for).
            // We can't just pick the latest attempt — a retried job has multiple records
            // sharing the same job_id, and the latest might already be completed.
            $monitor = JobMonitor::where('job_id', $jobId)
                ->where('status', 'processing')
                ->orderByDesc('attempt')
                ->first();

            // Fallback: if already transitioned to completed/failed (race with other listeners),
            // pick the most recent attempt that still has no metrics set.
            if ($monitor === null) {
                $monitor = JobMonitor::where('job_id', $jobId)
                    ->whereNull('cpu_time_ms')
                    ->orderByDesc('attempt')
                    ->first();
            }

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
