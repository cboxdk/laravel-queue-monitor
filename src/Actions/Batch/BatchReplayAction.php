<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Actions\Batch;

use PHPeek\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class BatchReplayAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private ReplayJobAction $replayAction,
    ) {}

    /**
     * Replay multiple jobs based on filters
     *
     * @return array{success: int, failed: int, errors: array<string, string>}
     */
    public function execute(JobFilterData $filters, int $maxJobs = 100): array
    {
        $jobs = $this->repository->query($filters);
        $jobs = $jobs->take($maxJobs);

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($jobs as $job) {
            try {
                $this->replayAction->execute($job->uuid);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[$job->uuid] = $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Replay jobs by UUIDs
     *
     * @param  array<string>  $uuids
     * @return array{success: int, failed: int, errors: array<string, string>}
     */
    public function executeByUuids(array $uuids): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($uuids as $uuid) {
            try {
                $this->replayAction->execute($uuid);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[$uuid] = $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
