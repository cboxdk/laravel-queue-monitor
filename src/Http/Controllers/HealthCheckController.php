<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Services\AlertingService;
use Cbox\LaravelQueueMonitor\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class HealthCheckController extends Controller
{
    public function __construct(
        private readonly HealthCheckService $healthCheck,
        private readonly AlertingService $alerting,
    ) {}

    /**
     * Get system health status
     */
    public function index(): JsonResponse
    {
        $health = $this->healthCheck->check();

        $status = $health['status'] === 'healthy' ? 200 : 503;

        return response()->json($health, $status);
    }

    /**
     * Get health score only
     */
    public function score(): JsonResponse
    {
        $score = $this->healthCheck->getHealthScore();

        return response()->json([
            'score' => $score,
            'status' => $score >= 80 ? 'healthy' : ($score >= 60 ? 'degraded' : 'unhealthy'),
        ]);
    }

    /**
     * Get active alerts
     */
    public function alerts(): JsonResponse
    {
        $alerts = $this->alerting->checkAlertConditions();

        return response()->json([
            'alerts' => $alerts,
            'has_critical' => $this->alerting->requiresAttention(),
            'count' => count($alerts),
        ]);
    }
}
