<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Batch;

use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class BatchDeleteAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Delete multiple jobs based on filters
     *
     * @return array{deleted: int, failed: int}
     */
    public function execute(JobFilterData $filters, int $maxJobs = 1000): array
    {
        /** @var int $chunkSize */
        $chunkSize = config('queue-monitor.batch.chunk_size', 100);
        $jobs = $this->repository->query($filters)->take($maxJobs);

        $deleted = 0;
        $failed = 0;

        $jobs->chunk($chunkSize)->each(function ($chunk) use (&$deleted, &$failed): void {
            foreach ($chunk as $job) {
                try {
                    if ($this->repository->delete($job->uuid)) {
                        $deleted++;
                    } else {
                        $failed++;
                    }
                } catch (\Throwable) {
                    $failed++;
                }
            }
        });

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }

    /**
     * Delete jobs by UUIDs
     *
     * @param  array<string>  $uuids
     * @return array{deleted: int, failed: int}
     */
    public function executeByUuids(array $uuids): array
    {
        /** @var int $chunkSize */
        $chunkSize = config('queue-monitor.batch.chunk_size', 100);
        $deleted = 0;
        $failed = 0;

        collect($uuids)->chunk($chunkSize)->each(function ($chunk) use (&$deleted, &$failed): void {
            foreach ($chunk as $uuid) {
                try {
                    if ($this->repository->delete($uuid)) {
                        $deleted++;
                    } else {
                        $failed++;
                    }
                } catch (\Throwable) {
                    $failed++;
                }
            }
        });

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }
}
