<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\DatabaseJob;

/**
 * Carries a released database job's rows across its change of job id.
 *
 * Every other driver keeps a job's id when it releases it. The database driver
 * deletes the job's row and inserts a new one (DatabaseQueue::deleteAndRelease),
 * so the redelivery arrives under an id the released attempt's row has never
 * seen, and the insert's id is not returned to the job. Rows are keyed by the
 * job id, so without help the released row would stay `queued` forever and the
 * redelivery would start a history of its own.
 *
 * The payload uuid survives the release. When an attempt is released, its
 * job's rows are re-keyed to a marker built from that uuid; when the
 * redelivery starts and finds no row under its new id, it takes over the rows
 * under the marker. Between the two, the rows' `job_id` reads
 * `released:{payload uuid}`.
 */
final readonly class ReleasedDatabaseJobs
{
    public const string MARKER_PREFIX = 'released:';

    /**
     * Re-key a released database job's rows so its redelivery can find them
     */
    public function markReleased(Job $job): void
    {
        $marker = $this->markerFor($job);

        if ($marker === null) {
            return;
        }

        JobMonitor::query()->where('job_id', JobKey::of($job))->update(['job_id' => $marker]);
    }

    /**
     * Take over the rows of the release this delivery follows
     *
     * Returns whether any row was taken over.
     */
    public function reclaim(Job $job, string $jobKey): bool
    {
        $marker = $this->markerFor($job);

        if ($marker === null) {
            return false;
        }

        return JobMonitor::query()->where('job_id', $marker)->update(['job_id' => $jobKey]) > 0;
    }

    /**
     * The key a released job's rows wait under, or null when the job keeps its id
     */
    public function markerFor(Job $job): ?string
    {
        if (! $job instanceof DatabaseJob) {
            return null;
        }

        $uuid = $job->uuid();

        return is_string($uuid) && $uuid !== '' ? self::MARKER_PREFIX.$uuid : null;
    }
}
