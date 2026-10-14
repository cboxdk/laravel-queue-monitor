<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

use Illuminate\Contracts\Queue\Job;

/**
 * Derives the key a queue job is recorded under (`job_monitor.job_id`).
 *
 * `Job::getJobId()` is not unique on every driver: Laravel's SyncJob returns
 * an empty string, so every sync job would share the key `''` and a lookup of
 * the latest row for it would match whichever sync job ran last — a sync job
 * that dispatches another sync job would get its completion or failure written
 * to the inner job's row. The rule is therefore:
 *
 * 1. the driver's job id, when it is non-empty;
 * 2. otherwise the payload uuid every Laravel payload carries;
 * 3. otherwise the job object's id, unique while the job is in memory.
 *
 * The database driver returns an integer id despite the contract's `string`
 * return type, so the id is normalized to a string.
 *
 * Must stay in step with `Cbox\LaravelQueueMetrics\Support\JobKey`, which
 * applies the same rule to the `jobId` of its JobMetricsCompleted and
 * JobMetricsFailed events: QueueMetricsSubscriber matches those events to
 * rows by this key, so a divergence silently drops CPU and memory figures.
 * That class is internal to laravel-queue-metrics, so the rule is copied
 * rather than depended on.
 */
final class JobKey
{
    public static function of(Job $job): string
    {
        $jobId = (string) $job->getJobId();

        if ($jobId !== '') {
            return $jobId;
        }

        $uuid = $job->uuid();

        if (is_string($uuid) && $uuid !== '') {
            return $uuid;
        }

        return 'object-'.spl_object_id($job);
    }
}
