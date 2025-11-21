<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Core;

use Carbon\Carbon;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\ExceptionData;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Throwable;

final readonly class RecordJobFailedAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private TagRepositoryContract $tagRepository,
    ) {}

    /**
     * Record when a job fails
     */
    public function execute(object $event): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

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
        if ($jobMonitor->tags !== null && count($jobMonitor->tags) > 0) {
            $this->tagRepository->storeTags($jobMonitor->id, $jobMonitor->tags);
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
