<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMetrics\Events\JobMetricsCompleted;
use Cbox\LaravelQueueMetrics\Events\JobMetricsFailed;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Services\Contracts\DashboardCacheServiceContract;
use Cbox\LaravelQueueMonitor\Support\InFlightAttempts;
use Cbox\LaravelQueueMonitor\Support\JobAttemptResolver;
use Illuminate\Events\Dispatcher;

/**
 * Captures per-job CPU time and memory (RSS) from laravel-queue-metrics (^2.3).
 *
 * This is the ONLY source of CPU/memory data — it comes from ProcessMetrics
 * (cboxdk/system-metrics) with per-process instrumentation, not getrusage().
 */
final class QueueMetricsSubscriber
{
    public function __construct(
        private readonly DashboardCacheServiceContract $dashboardCache,
        private readonly InFlightAttempts $inFlightAttempts,
        private readonly JobAttemptResolver $attempts,
    ) {}

    public function handleJobMetricsCompleted(JobMetricsCompleted $event): void
    {
        $this->updateJobMetrics($event->jobId, $event->cpuTimeMs, $event->memoryMb, $event->workerMemoryLimitMb);
    }

    public function handleJobMetricsFailed(JobMetricsFailed $event): void
    {
        $this->updateJobMetrics($event->jobId, $event->cpuTimeMs, $event->memoryMb, $event->workerMemoryLimitMb);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(JobMetricsCompleted::class, [self::class, 'handleJobMetricsCompleted']);
        $events->listen(JobMetricsFailed::class, [self::class, 'handleJobMetricsFailed']);
    }

    private function updateJobMetrics(string $jobId, float $cpuTimeMs, float $memoryMb, ?float $workerMemoryLimitMb = null): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        try {
            $monitor = $this->findMonitor($jobId);

            if ($monitor === null) {
                return;
            }

            $data = [
                'cpu_time_ms' => round($cpuTimeMs, 2),
                'memory_peak_mb' => round($memoryMb, 2),
            ];
            if ($workerMemoryLimitMb !== null) {
                $data['worker_memory_limit_mb'] = round($workerMemoryLimitMb, 2);
            }
            $monitor->update($data);
            $this->dashboardCache->bust();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Find the row of the attempt these metrics were measured on
     *
     * The event carries the job key but not the attempt. It is raised in the
     * worker that ran the job, so the attempt this process started (recorded
     * by JobProcessingListener) is the one measured — even when the broker
     * redelivered the job to another worker, whose newer attempt is the latest
     * row and may well be the one still `processing`.
     */
    private function findMonitor(string $jobId): ?JobMonitor
    {
        $attempt = $this->inFlightAttempts->attemptFor($jobId);

        if ($attempt !== null) {
            $monitor = $this->attempts->findAttempt($jobId, $attempt);

            if ($monitor !== null) {
                return $monitor;
            }
        }

        // Without a recorded attempt (the start was not seen by this process,
        // or the repository cannot look up attempts), take the attempt that is
        // currently processing. A retried job has several rows sharing the
        // job_id, and the latest might already be completed.
        $monitor = JobMonitor::where('job_id', $jobId)
            ->where('status', 'processing')
            ->orderByDesc('id')
            ->first();

        // Fallback: if already transitioned to completed/failed (race with other
        // listeners), pick the most recent attempt that still has no metrics set.
        return $monitor ?? JobMonitor::where('job_id', $jobId)
            ->whereNull('cpu_time_ms')
            ->orderByDesc('id')
            ->first();
    }
}
