<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Batch;

use Cbox\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

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
        /** @var int $chunkSize */
        $chunkSize = config('queue-monitor.batch.chunk_size', 100);
        $jobs = $this->repository->query($filters)->take($maxJobs);

        $success = 0;
        $failed = 0;
        $errors = [];

        $jobs->chunk($chunkSize)->each(function ($chunk) use (&$success, &$failed, &$errors): void {
            foreach ($chunk as $job) {
                try {
                    $this->replayAction->execute($job->uuid);
                    $success++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[$job->uuid] = $e->getMessage();
                }
            }
        });

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
        /** @var int $chunkSize */
        $chunkSize = config('queue-monitor.batch.chunk_size', 100);
        $success = 0;
        $failed = 0;
        $errors = [];

        collect($uuids)->chunk($chunkSize)->each(function ($chunk) use (&$success, &$failed, &$errors): void {
            foreach ($chunk as $uuid) {
                try {
                    $this->replayAction->execute($uuid);
                    $success++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[$uuid] = $e->getMessage();
                }
            }
        });

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
