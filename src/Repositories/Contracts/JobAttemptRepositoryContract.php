<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Repositories\Contracts;

use Cbox\LaravelQueueMonitor\Models\JobMonitor;

/**
 * Looks up the row of one specific attempt of a job.
 *
 * A broker redelivers a message whose visibility timeout (SQS) or retry_after
 * (Redis, database) lapses while the first worker is still running it, so two
 * attempts of one job can be in flight at once. Each worker's outcome has to
 * land on its own attempt's row, which "the latest attempt" cannot guarantee.
 *
 * This is a separate contract rather than a method on
 * JobMonitorRepositoryContract because that contract is documented as
 * implementable by hosts, and adding a method to it would break every custom
 * implementation. A custom JobMonitorRepositoryContract that does not also
 * implement this contract keeps the previous behaviour: outcomes are written to
 * the latest attempt's row. Implement it alongside to get per-attempt matching.
 */
interface JobAttemptRepositoryContract
{
    /**
     * Find the row recorded for one attempt of a job, or null when none exists
     *
     * Accepts int|string because the database queue driver returns an
     * integer job id while other drivers return a string. When several rows
     * share the attempt number, the most recently created one is returned.
     */
    public function findAttemptByJobId(int|string $jobId, int $attempt): ?JobMonitor;
}
