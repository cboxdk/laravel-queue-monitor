<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\AlertingServiceContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\HealthCheckServiceContract;
use Cbox\LaravelQueueMonitor\Services\Contracts\InfrastructureServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Handles health checks and infrastructure monitoring endpoints.
 *
 * Provides health score, SLA compliance, worker utilization, queue capacity,
 * and scaling data for the health and infrastructure tabs.
 */
class DashboardHealthController extends Controller
{
    public function __construct(
        private readonly InfrastructureServiceContract $infrastructureService,
        private readonly StatisticsRepositoryContract $statsRepository,
        private readonly HealthCheckServiceContract $healthService,
        private readonly AlertingServiceContract $alertingService,
    ) {}

    /**
     * Health tab: health score, status, checks, alerts
     */
    public function health(): JsonResponse
    {
        $healthCheck = $this->healthService->check();
        $alerts = $this->alertingService->checkAlertConditions();

        // Score from the checks we already ran, rather than running them again.
        $score = $this->healthService->scoreFromChecks($healthCheck['checks']);

        return response()->json([
            'score' => $score,
            'status' => $healthCheck['status'],
            'checks' => $healthCheck['checks'],
            'alerts' => $alerts,
        ]);
    }

    /**
     * Infrastructure tab: worker utilization, queue capacity, SLA compliance
     */
    public function infrastructure(): JsonResponse
    {
        $data = [
            'workers' => $this->infrastructureService->getWorkerData(),
            'worker_types' => $this->infrastructureService->getWorkerTypeBreakdown(),
            'queues' => $this->infrastructureService->getQueueInfraData(),
            'sla' => $this->infrastructureService->getSlaData(),
            'scaling' => array_intersect_key(
                $this->infrastructureService->getScalingData(),
                array_flip(['utilization', 'breach_severity']),
            ),
            'capacity' => $this->infrastructureService->getCapacityData(),
            'cluster' => $this->infrastructureService->getClusterData(),
        ];

        return response()->json($data);
    }

    /**
     * Autoscale tab: cluster orchestration, scaling signals, leader election
     */
    public function autoscale(): JsonResponse
    {
        $scaling = $this->infrastructureService->getScalingData();
        $cluster = $this->infrastructureService->getClusterData();
        $live = $this->infrastructureService->getLiveClusterState();

        return response()->json([
            'scaling' => $scaling,
            'cluster' => $cluster,
            'live' => $live,
            'sla' => $this->infrastructureService->getSlaData(),
            'available' => ($scaling['has_autoscale'] ?? false) || (is_array($cluster) && ($cluster['has_cluster'] ?? false)) || $live !== null,
        ]);
    }

    /** Time ranges (in minutes) the scaling timeline may request. */
    private const TIMELINE_RANGES = [15, 60, 360, 1440];

    /**
     * Scaling timeline for one queue: per-minute job volume, duration, and
     * memory buckets correlated with the autoscaler's worker decisions.
     */
    public function autoscaleTimeline(Request $request): JsonResponse
    {
        $queue = (string) $request->query('queue', '');

        if ($queue === '') {
            return response()->json(['message' => 'The queue parameter is required.'], 422);
        }

        $minutes = (int) $request->query('minutes', '60');
        if (! in_array($minutes, self::TIMELINE_RANGES, true)) {
            $minutes = 60;
        }

        $timeline = $this->statsRepository->getQueueTimeline($queue, $minutes);
        $scaling = $this->infrastructureService->getScalingTimeline($queue, $minutes);

        return response()->json([
            'queue' => $queue,
            'minutes' => $minutes,
            'buckets' => $timeline['buckets'],
            'live' => $timeline['live'],
            'memory_limit_mb' => $timeline['memory_limit_mb'],
            'workers' => $scaling['events'],
            'worker_range' => $scaling['worker_range'],
        ]);
    }
}
