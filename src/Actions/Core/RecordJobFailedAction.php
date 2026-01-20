<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\DataTransferObjects\ExceptionData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Jobs\StoreJobTagsJob;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Throwable;

final readonly class RecordJobFailedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private TagRepositoryContract $tagRepository,
    ) {}

    /**
     * Record when a job fails
     *
     * Note: Caller (listener) is responsible for checking if monitoring is enabled.
     */
    public function execute(object $event): void
    {
        $job = $event->job ?? null;
        $exception = $event->exception ?? null;

        if ($job === null) {
            return;
        }

        $jobId = $job->getJobId();
        $jobMonitor = $this->repository->findByJobId($jobId);

        if ($jobMonitor === null) {
            return;
        }

        $completedAt = now();
        $durationMs = $this->calculateDuration($jobMonitor->started_at, $completedAt);
        $exceptionData = $exception instanceof Throwable ? ExceptionData::fromThrowable($exception) : null;

        $this->repository->update($jobMonitor->uuid, [
            'status' => JobStatus::FAILED,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
            'exception_class' => $exceptionData?->class,
            'exception_message' => $exceptionData?->message,
            'exception_trace' => $exceptionData?->trace,
        ]);

        // Store normalized tags if present
        $this->storeTagsIfPresent($jobMonitor->id, $jobMonitor->tags);
    }

    /**
     * Store tags either synchronously or via deferred job
     *
     * @param  array<string>|null  $tags
     */
    private function storeTagsIfPresent(int $jobMonitorId, ?array $tags): void
    {
        if ($tags === null || count($tags) === 0) {
            return;
        }

        if (config('queue-monitor.deferred_tag_storage', false)) {
            StoreJobTagsJob::dispatch($jobMonitorId, $tags);
        } else {
            $this->tagRepository->storeTags($jobMonitorId, $tags);
        }
    }

    /**
     * Calculate duration in milliseconds
     */
    private function calculateDuration(?Carbon $startedAt, Carbon $completedAt): ?int
    {
        if ($startedAt === null) {
            return null;
        }

        return (int) $startedAt->diffInMilliseconds($completedAt);
    }
}
