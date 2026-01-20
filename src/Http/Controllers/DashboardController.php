<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Utilities\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly JobMonitorRepositoryContract $jobRepository,
        private readonly StatisticsRepositoryContract $statsRepository,
    ) {}

    /**
     * Display the dashboard view
     */
    public function index(): View
    {
        return view('queue-monitor::web.dashboard');
    }

    /**
     * Get real-time metrics for the dashboard
     */
    public function metrics(): JsonResponse
    {
        // Global Stats
        $globalStats = $this->statsRepository->getGlobalStatistics();

        // Queue Health
        $queueHealth = $this->statsRepository->getQueueHealth();

        // Recent Jobs Table
        $recentJobs = $this->jobRepository->getRecentJobs((int) config('queue-monitor.ui.per_page', 35))
            ->map(function ($job) {
                return [
                    'uuid' => $job->uuid,
                    'job_class' => $job->getShortJobClass(),
                    'queue' => $job->queue,
                    'status' => [
                        'value' => $job->status->value,
                        'label' => $job->status->label(),
                        'color' => $job->status->color(),
                    ],
                    'worker_type' => [
                        'value' => $job->worker_type->value,
                        'label' => $job->worker_type->label(),
                        'icon' => $job->worker_type->icon(), // Assumes we have an icon() method on Enum
                    ],
                    'server' => $job->server_name,
                    'duration' => $job->duration_ms ? number_format($job->duration_ms).'ms' : '-',
                    'queued_at' => $job->queued_at->diffForHumans(),
                    'error' => $job->exception_message,
                    'is_failed' => $job->isFailed(),
                ];
            });

        // Chart Data: Jobs per minute (Last hour)
        // Note: Ideally this should come from a specialized repository method
        // For now, we simulate a simple trend if not available, or implement a proper aggregation
        $chartData = $this->statsRepository->getJobClassStatistics(); // Reusing this for now as placeholder for distribution

        return response()->json([
            'stats' => $globalStats,
            'queues' => $queueHealth,
            'recent_jobs' => $recentJobs,
            'charts' => [
                'distribution' => $chartData,
            ],
        ]);
    }

    /**
     * Get redacted payload for a specific job
     */
    public function payload(string $uuid): JsonResponse
    {
        $job = $this->jobRepository->findByUuid($uuid);

        if ($job === null || empty($job->payload)) {
            return response()->json(['payload' => []]);
        }

        /** @var array<string> $sensitiveKeys */
        $sensitiveKeys = config('queue-monitor.api.sensitive_keys', []);

        return response()->json([
            'payload' => PayloadRedactor::redact($job->payload, $sensitiveKeys),
            'exception' => $job->exception_trace,
        ]);
    }
}
