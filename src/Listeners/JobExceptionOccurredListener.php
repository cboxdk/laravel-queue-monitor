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

            // An intermediate failure leaves the row `processing`; the next
            // attempt's start closes it as FAILED. A row already closed as
            // TIMEOUT without an exception was closed by inference — the broker
            // redelivered the job while this worker was still running it, or a
            // stuck-job resolution gave up on it — and nothing will close it
            // again, so the real outcome replaces the inference here.
            if ($monitor->status === JobStatus::TIMEOUT) {
                $completedAt = now();

                $data['status'] = JobStatus::FAILED;
                $data['completed_at'] = $completedAt;
                $data['duration_ms'] = $monitor->started_at !== null
                    ? max(0, (int) $monitor->started_at->diffInMilliseconds($completedAt))
                    : null;
            }

            $monitor->update($data);
            $this->dashboardCache->bust();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
