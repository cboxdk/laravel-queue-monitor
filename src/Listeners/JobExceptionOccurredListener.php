<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Listeners;

use Cbox\LaravelQueueMonitor\DataTransferObjects\ExceptionData;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
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
    public function handle(JobExceptionOccurred $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        try {
            $job = $event->job;
            $jobId = $job->getJobId();
            $exception = $event->exception;
            $exceptionData = ExceptionData::fromThrowable($exception);

            // Find the current processing attempt for this job
            $monitor = JobMonitor::where('job_id', $jobId)
                ->orderByDesc('attempt')
                ->orderByDesc('created_at')
                ->first();

            if ($monitor === null) {
                return;
            }

            // Only update if we don't already have exception data (avoid overwriting)
            if ($monitor->exception_class !== null) {
                return;
            }

            $monitor->update([
                'exception_class' => $exceptionData->class,
                'exception_message' => $exceptionData->message,
                'exception_trace' => $exceptionData->trace,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
