<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Http\Resources\JobMonitorCollection;
use Cbox\LaravelQueueMonitor\Http\Resources\JobMonitorResource;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class JobMonitorController extends Controller
{
    public function __construct(
        private readonly JobMonitorRepositoryContract $repository,
    ) {}

    /**
     * List jobs with optional filtering
     */
    public function index(Request $request): JobMonitorCollection
    {
        $filters = JobFilterData::fromRequest($request->all());

        $jobs = $this->repository->query($filters);
        $total = $this->repository->count($filters);

        return (new JobMonitorCollection($jobs))->additional([
            'meta' => [
                'total' => $total,
                'limit' => $filters->limit,
                'offset' => $filters->offset,
                'has_filters' => $filters->hasFilters(),
            ],
        ]);
    }

    /**
     * Get job details
     */
    public function show(string $uuid): JobMonitorResource
    {
        $job = $this->repository->findByUuid($uuid);

        if ($job === null) {
            abort(404, "Job with UUID {$uuid} not found");
        }

        return new JobMonitorResource($job);
    }

    /**
     * Delete a job
     */
    public function destroy(string $uuid): JsonResponse
    {
        $deleted = $this->repository->delete($uuid);

        if (! $deleted) {
            abort(404, "Job with UUID {$uuid} not found");
        }

        return response()->json([
            'message' => 'Job deleted successfully',
        ]);
    }

    /**
     * Get retry chain for a job
     */
    public function retryChain(string $uuid): JobMonitorCollection
    {
        $chain = $this->repository->getRetryChain($uuid);

        return new JobMonitorCollection($chain);
    }

    /**
     * Get failed jobs
     */
    public function failed(Request $request): JobMonitorCollection
    {
        $limitValue = $request->input('limit', 100);
        $limit = is_numeric($limitValue) ? (int) $limitValue : 100;

        $jobs = $this->repository->getFailedJobs($limit);

        return new JobMonitorCollection($jobs);
    }

    /**
     * Get recent jobs
     */
    public function recent(Request $request): JobMonitorCollection
    {
        $limitValue = $request->input('limit', 100);
        $limit = is_numeric($limitValue) ? (int) $limitValue : 100;

        $jobs = $this->repository->getRecentJobs($limit);

        return new JobMonitorCollection($jobs);
    }
}
