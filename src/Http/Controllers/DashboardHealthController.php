<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Services\AlertingService;
use Cbox\LaravelQueueMonitor\Services\HealthCheckService;
use Cbox\LaravelQueueMonitor\Services\InfrastructureService;
use Illuminate\Http\JsonResponse;
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
        private readonly InfrastructureService $infrastructureService,
    ) {}

    /**
     * Health tab: health score, status, checks, alerts
     */
    public function health(): JsonResponse
    {
        $healthService = app(HealthCheckService::class);
        $alertingService = app(AlertingService::class);

        $healthCheck = $healthService->check();
        $score = $healthService->getHealthScore();
        $alerts = $alertingService->checkAlertConditions();

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
            'scaling' => $this->infrastructureService->getScalingData(),
            'capacity' => $this->infrastructureService->getCapacityData(),
        ];

        return response()->json($data);
    }
}
