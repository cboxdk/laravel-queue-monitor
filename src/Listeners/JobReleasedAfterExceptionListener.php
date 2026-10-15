<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\Support\JobAttemptResolver;
use Cbox\LaravelQueueMonitor\Support\ReleasedDatabaseJobs;
use Illuminate\Queue\Events\JobReleasedAfterException;

/**
 * Lets the redelivery of a database job released on an exception find its rows.
 *
 * The attempt's row was already closed as FAILED on JobExceptionOccurred. The
 * database driver gives the redelivery a new job id, so the job's rows are
 * re-keyed for it (see ReleasedDatabaseJobs). An attempt a redelivery already
 * superseded is left alone: the job's rows belong to the newer attempt now.
 */
final class JobReleasedAfterExceptionListener
{
    public function __construct(
        private readonly JobAttemptResolver $attempts,
        private readonly ReleasedDatabaseJobs $releasedDatabaseJobs,
    ) {}

    public function handle(JobReleasedAfterException $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        try {
            if ($this->releasedDatabaseJobs->markerFor($event->job) === null) {
                return;
            }

            $row = $this->attempts->rowFor($event->job);

            if ($row === null || $this->attempts->isSuperseded($row)) {
                return;
            }

            $this->releasedDatabaseJobs->markReleased($event->job);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
