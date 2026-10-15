<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\DataTransferObjects\ExceptionData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Services\Contracts\DashboardCacheServiceContract;
use Cbox\LaravelQueueMonitor\Support\JobAttemptResolver;
use Illuminate\Queue\Events\JobExceptionOccurred;

/**
 * Captures exception data on EVERY failure, including intermediate retries.
 *
 * Laravel's JobFailed event only fires on the final attempt.
 * JobExceptionOccurred fires on every exception, so we can record
 * why each individual attempt failed — critical for the attempts trail.
 */
final class JobExceptionOccurredListener
{
    public function __construct(
        private readonly DashboardCacheServiceContract $dashboardCache,
        private readonly JobAttemptResolver $attempts,
    ) {}

    public function handle(JobExceptionOccurred $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        try {
            $exceptionData = ExceptionData::fromThrowable($event->exception);

            // The row of the attempt that threw, not the latest attempt: a
            // redelivery may have started a newer attempt while this one ran.
            $monitor = $this->attempts->rowFor($event->job);

            if ($monitor === null) {
                return;
            }

            // Only update if we don't already have exception data (avoid overwriting)
            if ($monitor->exception_class !== null) {
                return;
            }

            $data = [
                'exception_class' => $exceptionData->class,
                'exception_message' => $exceptionData->message,
                'exception_trace' => $exceptionData->trace,
            ];

            // The attempt is over. On a worker, Laravel either already failed
            // it (JobFailed fires before this event for a final attempt and
            // records the exception, so this listener returned above) or
            // releases it right after this event for a retry. The sync driver,
            // and the deferred and background drivers built on it, raise this
            // event first and JobFailed after it; the failed action then keeps
            // the duration recorded here. Either way the row is closed now,
            // with the duration of the attempt itself. Waiting for the next
            // attempt's start would add the backoff and the wait for
            // redelivery to the duration, and leave the row `processing`
            // (counted as stuck) when no further attempt ever starts.
            //
            // A row already closed as TIMEOUT without an exception was closed
            // by inference — the broker redelivered the job while this worker
            // was still running it, or a stuck-job resolution gave up on it —
            // and the real outcome replaces the inference here.
            if ($monitor->status === JobStatus::PROCESSING || $monitor->status === JobStatus::TIMEOUT) {
                $completedAt = now();

                $data['status'] = JobStatus::FAILED;
                $data['completed_at'] = $completedAt;
                $data['duration_ms'] = $this->attempts->durationMs($event->job, $monitor, $completedAt);
                $this->attempts->attemptEnded($event->job, $monitor);
            }

            $monitor->update($data);
            $this->dashboardCache->bust();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
