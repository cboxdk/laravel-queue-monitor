<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Actions\Batch\BatchDeleteAction;
use Cbox\LaravelQueueMonitor\Actions\Batch\BatchReplayAction;
use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
        $maxJobsLimit = $this->maxJobsLimit();

        $validated = $request->validate($this->batchRules($maxJobsLimit));

        if (isset($validated['uuids'])) {
            $result = $this->batchReplayAction->executeByUuids($validated['uuids']);
        } else {
            $filters = JobFilterData::fromRequest($validated['filters'] ?? []);
            $maxJobs = $validated['max_jobs'] ?? min(100, $maxJobsLimit);

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
        $maxJobsLimit = $this->maxJobsLimit();

        $validated = $request->validate($this->batchRules($maxJobsLimit));

        if (isset($validated['uuids'])) {
            $result = $this->batchDeleteAction->executeByUuids($validated['uuids']);
        } else {
            $filters = JobFilterData::fromRequest($validated['filters'] ?? []);
            $maxJobs = $validated['max_jobs'] ?? $maxJobsLimit;

            $result = $this->batchDeleteAction->execute($filters, $maxJobs);
        }

        return response()->json([
            'message' => 'Batch delete completed',
            'deleted' => $result['deleted'],
            'failed' => $result['failed'],
        ]);
    }

    private function maxJobsLimit(): int
    {
        $value = config('queue-monitor.batch.max_jobs', 1000);

        return is_numeric($value) ? max(1, (int) $value) : 1000;
    }

    /**
     * Batch actions are destructive, so an unrecognised filter value must be
     * rejected, never silently dropped — a dropped status filter widens the
     * match to every job instead of narrowing it.
     *
     * @return array<string, string>
     */
    private function batchRules(int $maxJobsLimit): array
    {
        return [
            'uuids' => 'sometimes|array|max:'.$maxJobsLimit,
            'uuids.*' => 'string|uuid',
            'filters' => 'sometimes|array',
            'filters.statuses' => 'sometimes|array',
            'filters.statuses.*' => 'string|in:'.implode(',', JobStatus::values()),
            'filters.queues' => 'sometimes|array',
            'filters.queues.*' => 'string',
            'filters.job_classes' => 'sometimes|array',
            'filters.job_classes.*' => 'string',
            'filters.server_names' => 'sometimes|array',
            'filters.server_names.*' => 'string',
            'filters.search' => 'sometimes|string',
            'filters.queued_after' => 'sometimes',
            'filters.queued_before' => 'sometimes',
            'filters.min_attempts' => 'sometimes|integer|min:1',
            'filters.min_duration_ms' => 'sometimes|integer|min:0',
            'max_jobs' => 'sometimes|integer|min:1|max:'.$maxJobsLimit,
        ];
    }
}
