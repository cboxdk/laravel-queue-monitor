<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Remembers which attempt of each job THIS process started, and when.
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
 * The start time is kept to the microsecond, so an outcome reported in this
 * process measures the attempt's duration exactly. The row's `started_at`
 * column only holds whole seconds, and a duration measured from it is up to a
 * second too long. The start time is forgotten once the attempt's outcome is
 * recorded; the attempt number is kept, because the metrics events can arrive
 * after the outcome.
 *
 * Registered as a singleton. A worker runs one job at a time (sync jobs can
 * nest a few deep), so the map is small; it is still capped so a long-lived
 * worker cannot grow it without bound.
 */
final class InFlightAttempts
{
    public const int MAX_TRACKED = 100;

    /** @var array<string, array{attempt: int, startedAt: CarbonImmutable|null}> */
    private array $attempts = [];

    public function remember(string $jobKey, int $attempt, ?CarbonInterface $startedAt = null): void
    {
        // Re-insert so the most recently started job is always last; eviction
        // then drops the job that started longest ago.
        unset($this->attempts[$jobKey]);
        $this->attempts[$jobKey] = [
            'attempt' => $attempt,
            'startedAt' => CarbonImmutable::instance($startedAt ?? now()),
        ];

        while (count($this->attempts) > self::MAX_TRACKED) {
            unset($this->attempts[array_key_first($this->attempts)]);
        }
    }

    public function attemptFor(string $jobKey): ?int
    {
        return $this->attempts[$jobKey]['attempt'] ?? null;
    }

    /**
     * When this process started that attempt of the job, or null when it did not
     */
    public function startedAtFor(string $jobKey, int $attempt): ?CarbonImmutable
    {
        $entry = $this->attempts[$jobKey] ?? null;

        if ($entry === null || $entry['attempt'] !== $attempt) {
            return null;
        }

        return $entry['startedAt'];
    }

    /**
     * Forget the start time of an attempt whose outcome has been recorded
     */
    public function forgetStart(string $jobKey, int $attempt): void
    {
        if (($this->attempts[$jobKey]['attempt'] ?? null) === $attempt) {
            $this->attempts[$jobKey]['startedAt'] = null;
        }
    }
}
