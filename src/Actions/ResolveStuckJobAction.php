<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions;

use Cbox\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class ResolveStuckJobAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private ReplayJobAction $replayAction,
    ) {}

    /**
     * @param  list<string>  $uuids
     * @return array{resolved: int, replayed: int, errors: list<string>}
     */
    public function execute(array $uuids, string $action): array
    {
        $resolved = 0;
        $replayed = 0;
        $errors = [];

        foreach ($uuids as $uuid) {
            $job = $this->repository->findByUuid($uuid);

            if ($job === null) {
                $errors[] = "Job {$uuid} not found";

                continue;
            }

            if ($job->status !== JobStatus::PROCESSING) {
                $errors[] = "Job {$uuid} is not stuck (status: {$job->status->value})";

                continue;
            }

            if ($action === 'delete') {
                $this->repository->delete($uuid);
                $resolved++;
            } elseif ($action === 'retry') {
                // completed_at is the schema column; finished_at does not exist
                // and was silently dropped, leaving resolved rows with no
                // completion timestamp or duration.
                $completedAt = now();
                $durationMs = $job->started_at !== null
                    ? max(0, (int) $job->started_at->diffInMilliseconds($completedAt))
                    : null;

                $this->repository->update($uuid, [
                    'status' => JobStatus::TIMEOUT,
                    'completed_at' => $completedAt,
                    'duration_ms' => $durationMs,
                ]);
                $resolved++;

                try {
                    $this->replayAction->execute($uuid);
                    $replayed++;
                } catch (\RuntimeException $e) {
                    $errors[] = "Job {$uuid} marked as timeout but replay failed: {$e->getMessage()}";
                }
            }
        }

        return [
            'resolved' => $resolved,
            'replayed' => $replayed,
            'errors' => $errors,
        ];
    }
}
