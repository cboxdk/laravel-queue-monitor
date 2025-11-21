<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PHPeek\LaravelQueueMonitor\Actions\Batch\BatchDeleteAction;
use PHPeek\LaravelQueueMonitor\Actions\Batch\BatchReplayAction;
use PHPeek\LaravelQueueMonitor\DataTransferObjects\JobFilterData;

class BatchOperationsController extends Controller
{
    public function __construct(
        private readonly BatchReplayAction $batchReplayAction,
        private readonly BatchDeleteAction $batchDeleteAction,
    ) {}

    /**
     * Batch replay jobs
     */
    public function batchReplay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuids' => 'sometimes|array',
            'uuids.*' => 'string|uuid',
            'filters' => 'sometimes|array',
            'max_jobs' => 'sometimes|integer|min:1|max:1000',
        ]);

        if (isset($validated['uuids'])) {
            $result = $this->batchReplayAction->executeByUuids($validated['uuids']);
        } else {
            $filters = JobFilterData::fromRequest($validated['filters'] ?? []);
            $maxJobs = $validated['max_jobs'] ?? 100;

            $result = $this->batchReplayAction->execute($filters, $maxJobs);
        }

        return response()->json([
            'message' => 'Batch replay completed',
            'success' => $result['success'],
            'failed' => $result['failed'],
            'errors' => $result['errors'],
        ]);
    }

    /**
     * Batch delete jobs
     */
    public function batchDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'uuids' => 'sometimes|array',
            'uuids.*' => 'string|uuid',
            'filters' => 'sometimes|array',
            'max_jobs' => 'sometimes|integer|min:1|max:1000',
        ]);

        if (isset($validated['uuids'])) {
            $result = $this->batchDeleteAction->executeByUuids($validated['uuids']);
        } else {
            $filters = JobFilterData::fromRequest($validated['filters'] ?? []);
            $maxJobs = $validated['max_jobs'] ?? 1000;

            $result = $this->batchDeleteAction->execute($filters, $maxJobs);
        }

        return response()->json([
            'message' => 'Batch delete completed',
            'deleted' => $result['deleted'],
            'failed' => $result['failed'],
        ]);
    }
}
