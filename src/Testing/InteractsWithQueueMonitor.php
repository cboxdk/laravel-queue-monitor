<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Testing;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;

/**
 * Assertions for what Queue Monitor recorded, so a consuming app can verify
 * job monitoring in its own feature tests without querying the tables by hand.
 *
 * Use it in a test that migrates the package tables (e.g. with RefreshDatabase);
 * the assertions read the real recorded state.
 *
 * ```php
 * use Cbox\LaravelQueueMonitor\Testing\InteractsWithQueueMonitor;
 *
 * uses(InteractsWithQueueMonitor::class);
 *
 * it('records the job', function () {
 *     dispatch(new SendInvoice($order));
 *     $this->assertJobRecorded(SendInvoice::class);
 * });
 * ```
 */
trait InteractsWithQueueMonitor
{
    /**
     * The recorded job monitors, optionally filtered by job class.
     *
     * @return Collection<int, JobMonitor>
     */
    protected function recordedJobs(?string $jobClass = null): Collection
    {
        $query = JobMonitor::query();

        if ($jobClass !== null) {
            $query->where('job_class', $jobClass);
        }

        return $query->get();
    }

    /**
     * Assert a job of the given class was recorded, optionally an exact number
     * of times.
     */
    protected function assertJobRecorded(string $jobClass, ?int $times = null): void
    {
        $count = $this->recordedJobs($jobClass)->count();

        if ($times === null) {
            Assert::assertGreaterThan(
                0,
                $count,
                "Expected a [{$jobClass}] job to be recorded, but none was."
            );

            return;
        }

        Assert::assertSame(
            $times,
            $count,
            "Expected [{$jobClass}] to be recorded {$times} time(s), but it was recorded {$count} time(s)."
        );
    }

    /**
     * Assert no job of the given class was recorded.
     */
    protected function assertJobNotRecorded(string $jobClass): void
    {
        $count = $this->recordedJobs($jobClass)->count();

        Assert::assertSame(
            0,
            $count,
            "Expected no [{$jobClass}] job to be recorded, but {$count} was."
        );
    }

    /**
     * Assert nothing was recorded at all.
     */
    protected function assertNothingRecorded(): void
    {
        $count = JobMonitor::query()->count();

        Assert::assertSame(0, $count, "Expected no jobs to be recorded, but {$count} was.");
    }

    /**
     * Assert a job of the given class was recorded with the given status.
     */
    protected function assertJobRecordedWithStatus(string $jobClass, JobStatus|string $status): void
    {
        $status = $status instanceof JobStatus ? $status : JobStatus::from($status);

        $exists = $this->recordedJobs($jobClass)
            ->contains(fn (JobMonitor $job): bool => $job->status === $status);

        Assert::assertTrue(
            $exists,
            "Expected a [{$jobClass}] job recorded with status [{$status->value}], but none matched."
        );
    }

    /**
     * Assert a job of the given class was recorded as failed or timed out.
     */
    protected function assertJobFailed(string $jobClass): void
    {
        $exists = $this->recordedJobs($jobClass)
            ->contains(fn (JobMonitor $job): bool => $job->isFailed());

        Assert::assertTrue(
            $exists,
            "Expected a [{$jobClass}] job to be recorded as failed, but none was."
        );
    }

    /**
     * Assert a job of the given class was recorded as completed.
     */
    protected function assertJobCompleted(string $jobClass): void
    {
        $this->assertJobRecordedWithStatus($jobClass, JobStatus::COMPLETED);
    }
}
