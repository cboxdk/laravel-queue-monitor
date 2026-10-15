<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Support\Facades\Cache;

/**
 * Connects a released database job's attempt to the delivery that follows it.
 *
 * Every other driver keeps a job's id when it releases it. The database driver
 * deletes the job's row and inserts a new one (DatabaseQueue::deleteAndRelease),
 * so the next delivery arrives under an id the released attempt's row has
 * never seen, and the insert's id is not returned to the job. The payload uuid
 * survives the release, so the two sides meet through it, in a cache shared by
 * the workers:
 *
 * - On release, the attempt's row is recorded under the uuid.
 * - When a delivery of a database job starts on a later attempt and finds no
 *   row under its own id, it takes over the released row: a row parked as
 *   `queued` is moved to the new id and reused, a row closed as `failed` stays
 *   where it is and the new attempt's row links to it.
 *
 * The new jobs row is inserted before the worker raises the event the release
 * is recorded on, so another worker can start the next delivery first. That
 * delivery records itself under the uuid too, and whichever side comes second
 * connects the two: the new row takes the place of a parked row, or links to
 * a failed one. Each side writes its own entry before reading the other's, so
 * at least one of them sees both.
 *
 * Only the released attempt's own row ever moves. Other rows of the job, such
 * as a slow attempt the broker redelivered under the same id, stay under the
 * id they ran with, so their late outcomes still find them. A moved row is
 * also remembered under its old id, for laravel-queue-metrics' figures, which
 * arrive under the id the released attempt ran with.
 *
 * Without a cache store the workers share (the `array` store, or a `file`
 * store across hosts) the two sides cannot meet; the released row then stays
 * `queued`, as it did before this was handled.
 */
final readonly class ReleasedDatabaseJobs
{
    /**
     * How long a release waits for its next delivery. A job released with a
     * longer delay is not connected to it.
     */
    public const int RELEASE_TTL_SECONDS = 7 * 86_400;

    /**
     * How long a delivery, or a moved row, can still be looked up.
     */
    public const int LINK_TTL_SECONDS = 86_400;

    /**
     * Record the row of a released attempt, and connect it if its next
     * delivery has already started
     */
    public function recordRelease(Job $job, JobMonitor $row): void
    {
        $uuid = $this->uuidOf($job);

        if ($uuid === null) {
            return;
        }

        $this->cache()->put($this->releasedKey($uuid), $row->id, self::RELEASE_TTL_SECONDS);

        $redeliveredId = $this->cache()->get($this->redeliveredKey($uuid));

        if (is_int($redeliveredId)) {
            $this->connect($uuid, $row->id, $redeliveredId);
        }
    }

    /**
     * The released row this delivery follows, taken over for its job id
     *
     * Runs inside the started action's transaction, before it creates a row.
     * A row parked as `queued` is moved to the new job id, and the caller
     * reuses it; any other row is returned as the attempt the new row follows.
     */
    public function adopt(Job $job, string $jobKey): ?JobMonitor
    {
        $uuid = $this->uuidOf($job);

        if ($uuid === null || $job->attempts() <= 1) {
            return null;
        }

        $releasedId = $this->cache()->get($this->releasedKey($uuid));

        if (! is_int($releasedId)) {
            return null;
        }

        /** @var JobMonitor|null $released */
        $released = JobMonitor::query()->whereKey($releasedId)->lockForUpdate()->first();

        if ($released === null || $released->attempt >= $job->attempts()) {
            return null;
        }

        $this->cache()->forget($this->releasedKey($uuid));

        if ($released->status === JobStatus::QUEUED && $released->job_id !== $jobKey) {
            if ($released->job_id !== null) {
                $this->rememberMove($job->getConnectionName(), $released->job_id, $released->id);
            }

            $released->update(['job_id' => $jobKey]);
        }

        return $released;
    }

    /**
     * Record a delivery that started without finding its released row, and
     * connect it if the release has been recorded since
     *
     * Called after the started action's transaction commits, so the row is
     * visible to the worker that records the release.
     */
    public function recordRedelivery(Job $job, JobMonitor $row): void
    {
        $uuid = $this->uuidOf($job);

        if ($uuid === null || $job->attempts() <= 1) {
            return;
        }

        $this->cache()->put($this->redeliveredKey($uuid), $row->id, self::LINK_TTL_SECONDS);

        $releasedId = $this->cache()->get($this->releasedKey($uuid));

        if (is_int($releasedId)) {
            $this->connect($uuid, $releasedId, $row->id);
        }
    }

    /**
     * The row a released attempt that ran under this job id was moved to
     */
    public function movedRow(string $connection, string $jobKey): ?JobMonitor
    {
        $rowId = $this->cache()->get($this->movedKey($connection, $jobKey));

        if (! is_int($rowId)) {
            return null;
        }

        /** @var JobMonitor|null */
        return JobMonitor::query()->find($rowId);
    }

    /**
     * Whether released attempts of this job are tracked here
     */
    public function tracks(Job $job): bool
    {
        return $this->uuidOf($job) !== null;
    }

    /**
     * Connect a released row to the row its next delivery created on its own
     *
     * A parked release did not end the job: the delivery's row takes its place
     * and the parked row is removed, which leaves the same single row a release
     * leaves on every other driver. A failed attempt is an outcome of its own:
     * it stays, and the delivery's row links to it.
     */
    private function connect(string $uuid, int $releasedId, int $redeliveredId): void
    {
        if ($releasedId === $redeliveredId) {
            return;
        }

        JobMonitor::transaction(function () use ($releasedId, $redeliveredId): void {
            /** @var JobMonitor|null $released */
            $released = JobMonitor::query()->whereKey($releasedId)->lockForUpdate()->first();
            /** @var JobMonitor|null $redelivered */
            $redelivered = JobMonitor::query()->whereKey($redeliveredId)->lockForUpdate()->first();

            if ($released === null || $redelivered === null || $redelivered->attempt <= $released->attempt) {
                return;
            }

            if ($released->status === JobStatus::QUEUED) {
                $redelivered->update([
                    'retried_from_id' => $redelivered->retried_from_id ?? $released->retried_from_id,
                    'queued_at' => $released->queued_at ?? $redelivered->queued_at,
                ]);
                $released->delete();

                return;
            }

            if ($redelivered->retried_from_id === null) {
                $redelivered->update(['retried_from_id' => $released->id]);
            }
        });

        $this->cache()->forget($this->releasedKey($uuid));
        $this->cache()->forget($this->redeliveredKey($uuid));
    }

    private function rememberMove(string $connection, string $jobKey, int $rowId): void
    {
        $this->cache()->put($this->movedKey($connection, $jobKey), $rowId, self::LINK_TTL_SECONDS);
    }

    private function uuidOf(Job $job): ?string
    {
        if (! $job instanceof DatabaseJob) {
            return null;
        }

        $uuid = $job->uuid();

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    private function releasedKey(string $uuid): string
    {
        return $this->prefix()."database-release:released:{$uuid}";
    }

    private function redeliveredKey(string $uuid): string
    {
        return $this->prefix()."database-release:redelivered:{$uuid}";
    }

    private function movedKey(string $connection, string $jobKey): string
    {
        return $this->prefix()."database-release:moved:{$connection}:{$jobKey}";
    }

    private function prefix(): string
    {
        $prefix = config('queue-monitor.cache.prefix', 'queue_monitor_');

        return is_string($prefix) ? $prefix : 'queue_monitor_';
    }

    private function cache(): Repository
    {
        /** @var string|null $store */
        $store = config('queue-monitor.cache.store');

        return $store !== null ? Cache::store($store) : Cache::store();
    }
}
