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
        $jobs = $this->repository->query($filters);
        $jobs = $jobs->take($maxJobs);

        $deleted = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            try {
                if ($this->repository->delete($job->uuid)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

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
        $deleted = 0;
        $failed = 0;

        foreach ($uuids as $uuid) {
            try {
                if ($this->repository->delete($uuid)) {
                    $deleted++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return [
            'deleted' => $deleted,
            'failed' => $failed,
        ];
    }
}
