<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use PHPeek\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use PHPeek\LaravelQueueMonitor\Events\JobReplayRequested;
use PHPeek\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

class JobReplayController extends Controller
{
    public function __construct(
        private readonly ReplayJobAction $replayAction,
        private readonly JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * Replay a job
     */
    public function __invoke(string $uuid): JsonResponse
    {
        try {
            $replayData = $this->replayAction->execute($uuid);

            $originalJob = $this->repository->findByUuid($uuid);

            if ($originalJob !== null) {
                event(new JobReplayRequested($originalJob, $replayData));
            }

            return response()->json([
                'message' => 'Job replayed successfully',
                'data' => $replayData->toArray(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Failed to replay job',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
