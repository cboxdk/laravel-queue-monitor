<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions;

use Cbox\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Exceptions\JobNotFoundException;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

final readonly class ResolveStuckJobAction
{
    public function __construct(
        private JobMonitorRepositoryContract $repository,
        private ReplayJobAction $replayAction,
    ) {}

    /**
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
                $this->repository->update($uuid, [
                    'status' => JobStatus::TIMEOUT,
                    'finished_at' => now(),
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
