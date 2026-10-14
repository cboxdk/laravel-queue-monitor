<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

/**
 * Remembers which attempt of each job THIS process started.
 *
 * laravel-queue-metrics reports a job's CPU and memory in JobMetricsCompleted /
 * JobMetricsFailed events that carry the job key but not the attempt. Those
 * events are raised synchronously in the worker that ran the job, so the
 * attempt that worker started is the attempt the figures belong to — even when
 * the broker has meanwhile redelivered the job to another worker, which is
 * running a newer attempt. JobProcessingListener records the attempt here as
 * the job starts, and the metrics subscriber reads it back.
 *
 * It is also the fallback for a driver whose Job::attempts() can no longer be
 * read once the job has been deleted: Beanstalkd asks the server for the job's
 * stats, which fails after CallQueuedHandler deletes a finished job.
 *
 * Registered as a singleton. A worker runs one job at a time (sync jobs can
 * nest a few deep), so the map is small; it is still capped so a long-lived
 * worker cannot grow it without bound.
 */
final class InFlightAttempts
{
    public const int MAX_TRACKED = 100;

    /** @var array<string, int> */
    private array $attempts = [];

    public function remember(string $jobKey, int $attempt): void
    {
        // Re-insert so the most recently started job is always last; eviction
        // then drops the job that started longest ago.
        unset($this->attempts[$jobKey]);
        $this->attempts[$jobKey] = $attempt;

        while (count($this->attempts) > self::MAX_TRACKED) {
            unset($this->attempts[array_key_first($this->attempts)]);
        }
    }

    public function attemptFor(string $jobKey): ?int
    {
        return $this->attempts[$jobKey] ?? null;
    }
}
