<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

use Carbon\CarbonInterface;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobAttemptRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Illuminate\Contracts\Queue\Job;

/**
 * Finds the row a worker's outcome belongs to: the row of the attempt that
 * worker is running, not merely the latest attempt of the job.
 *
 * On SQS (visibility timeout) and Redis or database (retry_after) a job that is
 * merely slow is redelivered while its first worker is still running it. The
 * redelivery opens a new attempt row, and when the first worker finally
 * reports, "the latest attempt" is the redelivery's row — the slow attempt's
 * outcome, duration and exception would be written onto it, and the slow
 * attempt's own row would never get its real outcome.
 *
 * Job::attempts() is the attempt the worker is running on every driver:
 * Redis increments the payload's attempts as it reserves the job (RedisJob
 * reports the pre-reserve count + 1), SQS reports ApproximateReceiveCount, the
 * database driver increments the row's attempts on reserve, Beanstalkd reports
 * the job's reserve count, and a sync job is always attempt 1.
 *
 * When no row with that attempt exists (a custom repository without
 * JobAttemptRepositoryContract, a row recorded before monitoring was enabled,
 * an attempt number the broker over-reported), the latest attempt's row is
 * used, which is the behaviour before per-attempt matching.
 */
final readonly class JobAttemptResolver
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private InFlightAttempts $inFlightAttempts,
    ) {}

    /**
     * The row of the attempt this job object is running, else the latest row
     */
    public function rowFor(Job $job): ?JobMonitor
    {
        $jobKey = JobKey::of($job);
        $attempt = $this->attemptOf($job, $jobKey);

        if ($attempt !== null) {
            $row = $this->findAttempt($jobKey, $attempt);

            if ($row !== null) {
                return $row;
            }
        }

        return $this->repository->findLatestAttemptByJobId($jobKey);
    }

    /**
     * The row of exactly this attempt, or null when there is none
     */
    public function findAttempt(string $jobKey, int $attempt): ?JobMonitor
    {
        if (! $this->repository instanceof JobAttemptRepositoryContract) {
            return null;
        }

        return $this->repository->findAttemptByJobId($jobKey, $attempt);
    }

    /**
     * Whether a newer attempt of the same job has a row of its own
     */
    public function isSuperseded(JobMonitor $row): bool
    {
        if ($row->job_id === null) {
            return false;
        }

        $latest = $this->repository->findLatestAttemptByJobId($row->job_id);

        return $latest !== null && $latest->uuid !== $row->uuid;
    }

    /**
     * How long the attempt in the row ran, in milliseconds, up to $endedAt
     *
     * Measured from the start this process recorded for that attempt when it
     * has one, which is exact. Otherwise — the outcome is reported by a
     * process that did not see the start, or after a restart — from the
     * row's `started_at`, which holds whole seconds and can make the
     * duration up to a second too long.
     */
    public function durationMs(Job $job, JobMonitor $row, CarbonInterface $endedAt): ?int
    {
        $startedAt = $this->inFlightAttempts->startedAtFor(JobKey::of($job), $row->attempt)
            ?? $row->started_at;

        if ($startedAt === null) {
            return null;
        }

        return max(0, (int) $startedAt->diffInMilliseconds($endedAt));
    }

    /**
     * Forget the start time of the attempt in the row; its outcome is recorded
     */
    public function attemptEnded(Job $job, JobMonitor $row): void
    {
        $this->inFlightAttempts->forgetStart(JobKey::of($job), $row->attempt);
    }

    /**
     * The attempt number the job is running
     *
     * Job::attempts() is authoritative, but Beanstalkd reads it from the
     * server, which fails once a finished job has been deleted; this process
     * recorded the attempt when the job started, so that is the fallback.
     */
    public function attemptOf(Job $job, ?string $jobKey = null): ?int
    {
        try {
            $attempt = $job->attempts();
        } catch (\Throwable) {
            $attempt = null;
        }

        if ($attempt !== null && $attempt > 0) {
            return $attempt;
        }

        return $this->inFlightAttempts->attemptFor($jobKey ?? JobKey::of($job));
    }
}
