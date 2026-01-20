<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateJobStatisticsAction;
use Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateQueueHealthAction;
use Cbox\LaravelQueueMonitor\Actions\Analytics\CalculateServerStatisticsAction;
use Cbox\LaravelQueueMonitor\Http\Resources\StatisticsResource;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;

class StatisticsController extends Controller
{
    public function __construct(
        private readonly CalculateJobStatisticsAction $jobStatsAction,
        private readonly CalculateServerStatisticsAction $serverStatsAction,
        private readonly CalculateQueueHealthAction $queueHealthAction,
        private readonly StatisticsRepositoryContract $statsRepository,
        private readonly TagRepositoryContract $tagRepository,
    ) {}

    /**
     * Get global statistics
     */
    public function global(): StatisticsResource
    {
        $stats = $this->jobStatsAction->execute();

        return new StatisticsResource($stats);
    }

    /**
     * Get per-server statistics
     */
    public function servers(Request $request): StatisticsResource
    {
        $serverName = $request->input('server');
        $server = is_string($serverName) ? $serverName : null;

        $stats = $this->serverStatsAction->execute($server);

        return new StatisticsResource($stats);
    }

    /**
     * Get per-queue statistics
     */
    public function queues(Request $request): StatisticsResource
    {
        $queue = $request->input('queue');

        $stats = $this->statsRepository->getQueueStatistics($queue);

        return new StatisticsResource($stats);
    }

    /**
     * Get per-job-class statistics
     */
    public function jobClasses(Request $request): StatisticsResource
    {
        $jobClass = $request->input('job_class');

        $stats = $this->statsRepository->getJobClassStatistics($jobClass);

        return new StatisticsResource($stats);
    }

    /**
     * Get queue health metrics
     */
    public function queueHealth(): StatisticsResource
    {
        $health = $this->queueHealthAction->execute();

        return new StatisticsResource($health);
    }

    /**
     * Get failure patterns
     */
    public function failurePatterns(): StatisticsResource
    {
        $patterns = $this->statsRepository->getFailurePatterns();

        return new StatisticsResource($patterns);
    }

    /**
     * Get tag analytics
     */
    public function tags(): StatisticsResource
    {
        $stats = $this->tagRepository->getTagStatistics();

        return new StatisticsResource($stats);
    }
}
