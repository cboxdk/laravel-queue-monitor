<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\AlertingService;
use Cbox\LaravelQueueMonitor\Utilities\PayloadRedactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;

/**
 * Handles overview metrics, job listing, job detail, analytics, and legacy payload endpoints.
 *
 * Provides all data-fetching endpoints for the jobs and analytics tabs of the
 * queue monitor dashboard.
 */
class DashboardMetricsController extends Controller
{
    public function __construct(
        private readonly JobMonitorRepositoryContract $jobRepository,
        private readonly StatisticsRepositoryContract $statsRepository,
        private readonly TagRepositoryContract $tagRepository,
    ) {}

    /**
     * Overview tab: stats, queues, alerts, recent jobs, charts
     */
    public function overview(): JsonResponse
    {
        $globalStats = $this->statsRepository->getGlobalStatistics();
        $queueHealth = $this->statsRepository->getQueueHealth();

        /** @var int $perPage */
        $perPage = config('queue-monitor.ui.per_page', 35);

        $recentJobs = $this->jobRepository->getRecentJobs($perPage)
            ->map(function ($job) {
                return [
                    'uuid' => $job->uuid,
                    'job_class' => $job->getShortJobClass(),
                    'full_job_class' => $job->job_class,
                    'queue' => $job->queue,
                    'status' => [
                        'value' => $job->status->value,
                        'label' => $job->status->label(),
                        'color' => $job->status->color(),
                    ],
                    'worker_type' => [
                        'value' => $job->worker_type->value,
                        'label' => $job->worker_type->label(),
                        'icon' => $job->worker_type->icon(),
                    ],
                    'server' => $job->server_name,
                    'duration_ms' => $job->duration_ms,
                    'cpu_time_ms' => $job->cpu_time_ms,
                    'memory_peak_mb' => $job->memory_peak_mb,
                    'queued_at' => $job->queued_at->diffForHumans(),
                    'attempt' => $job->attempt,
                    'max_attempts' => $job->max_attempts,
                    'error' => $job->exception_message,
                    'is_failed' => $job->isFailed(),
                ];
            });

        $chartData = $this->statsRepository->getJobClassStatistics();

        $alertingService = app(AlertingService::class);
        $alerts = $alertingService->checkAlertConditions();

        return response()->json([
            'stats' => $globalStats,
            'queues' => $queueHealth,
            'alerts' => $alerts,
            'recent_jobs' => $recentJobs,
            'charts' => [
                'distribution' => $chartData,
                'throughput' => $this->statsRepository->getThroughputByMinute(60),
            ],
        ]);
    }

    /**
     * Jobs tab: paginated jobs with filters
     */
    public function jobs(Request $request): JsonResponse
    {
        $filters = JobFilterData::fromRequest($request->all());

        $jobs = $this->jobRepository->query($filters);
        $total = $this->jobRepository->count($filters);

        $data = $jobs->map(function ($job) {
            return [
                'uuid' => $job->uuid,
                'job_class' => $job->getShortJobClass(),
                'full_job_class' => $job->job_class,
                'queue' => $job->queue,
                'connection' => $job->connection,
                'status' => [
                    'value' => $job->status->value,
                    'label' => $job->status->label(),
                    'color' => $job->status->color(),
                ],
                'worker_type' => [
                    'value' => $job->worker_type->value,
                    'label' => $job->worker_type->label(),
                    'icon' => $job->worker_type->icon(),
                ],
                'attempt' => $job->attempt,
                'max_attempts' => $job->max_attempts,
                'server' => $job->server_name,
                'duration_ms' => $job->duration_ms,
                'duration' => $job->duration_ms ? number_format($job->duration_ms).'ms' : '-',
                'cpu_time_ms' => $job->cpu_time_ms,
                'memory_peak_mb' => $job->memory_peak_mb,
                'queued_at' => $job->queued_at->toIso8601String(),
                'started_at' => $job->started_at?->toIso8601String(),
                'completed_at' => $job->completed_at?->toIso8601String(),
                'error' => $job->exception_message,
                'is_failed' => $job->isFailed(),
            ];
        });

        // Provide distinct queue names for the filter dropdown (cached to avoid full table scan)
        $availableQueues = Cache::remember('queue_monitor:available_queues', 60, fn () => JobMonitor::query()
            ->distinct()
            ->whereNotNull('queue')
            ->pluck('queue')
            ->sort()
            ->values()
            ->all()
        );

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $total,
                'limit' => $filters->limit,
                'offset' => $filters->offset,
                'available_queues' => $availableQueues,
            ],
        ]);
    }

    /**
     * Job slide-over: full detail with redacted payload, exception, retry chain
     */
    public function jobDetail(string $uuid): JsonResponse
    {
        $job = $this->jobRepository->findByUuid($uuid);

        if ($job === null) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        /** @var array<string> $sensitiveKeys */
        $sensitiveKeys = config('queue-monitor.api.sensitive_keys', []);

        $payload = $job->payload !== null
            ? PayloadRedactor::redact($job->payload, $sensitiveKeys)
            : null;

        $retryChain = $this->jobRepository->getRetryChain($uuid)
            ->map(function ($retryJob) use ($uuid) {
                return [
                    'uuid' => $retryJob->uuid,
                    'attempt' => $retryJob->attempt,
                    'status' => [
                        'value' => $retryJob->status->value,
                        'label' => $retryJob->status->label(),
                        'color' => $retryJob->status->color(),
                    ],
                    'duration_ms' => $retryJob->duration_ms,
                    'memory_peak_mb' => $retryJob->memory_peak_mb,
                    'server_name' => $retryJob->server_name,
                    'worker_id' => $retryJob->worker_id,
                    'started_at' => $retryJob->started_at?->toIso8601String(),
                    'completed_at' => $retryJob->completed_at?->toIso8601String(),
                    'exception_class' => $retryJob->exception_class,
                    'exception_message' => $retryJob->exception_message,
                    'exception_trace' => PayloadRedactor::redactTrace($retryJob->exception_trace),
                    'wait_time_ms' => $retryJob->started_at !== null
                        ? (int) $retryJob->queued_at->diffInMilliseconds($retryJob->started_at)
                        : null,
                    'is_current' => $retryJob->uuid === $uuid,
                ];
            });

        return response()->json([
            'job' => [
                'uuid' => $job->uuid,
                'job_class' => $job->job_class,
                'short_job_class' => $job->getShortJobClass(),
                'display_name' => $job->display_name,
                'queue' => $job->queue,
                'connection' => $job->connection,
                'status' => [
                    'value' => $job->status->value,
                    'label' => $job->status->label(),
                    'color' => $job->status->color(),
                ],
                'worker_type' => [
                    'value' => $job->worker_type->value,
                    'label' => $job->worker_type->label(),
                    'icon' => $job->worker_type->icon(),
                ],
                'attempt' => $job->attempt,
                'max_attempts' => $job->max_attempts,
                'server' => $job->server_name,
                'worker_id' => $job->worker_id,
                'metrics' => [
                    'duration_ms' => $job->duration_ms,
                    'cpu_time_ms' => $job->cpu_time_ms,
                    'memory_peak_mb' => $job->memory_peak_mb,
                    'file_descriptors' => $job->file_descriptors,
                    'wait_time_ms' => $job->started_at !== null
                        ? (int) $job->queued_at->diffInMilliseconds($job->started_at)
                        : null,
                    'total_time_ms' => $job->completed_at !== null
                        ? (int) $job->queued_at->diffInMilliseconds($job->completed_at)
                        : null,
                    'delay_ms' => $job->available_at !== null
                        ? (int) $job->queued_at->diffInMilliseconds($job->available_at)
                        : null,
                    'pickup_latency_ms' => $job->started_at !== null && $job->available_at !== null
                        ? (int) $job->available_at->diffInMilliseconds($job->started_at)
                        : null,
                ],
                'timestamps' => [
                    'queued_at' => $job->queued_at->toIso8601String(),
                    'available_at' => $job->available_at?->toIso8601String(),
                    'started_at' => $job->started_at?->toIso8601String(),
                    'completed_at' => $job->completed_at?->toIso8601String(),
                ],
                'tags' => $job->tags,
                'is_failed' => $job->isFailed(),
                'is_retryable' => $job->isRetryable(),
            ],
            'payload' => $payload,
            'exception' => $job->isFailed() ? [
                'class' => $job->exception_class,
                'short_class' => $job->getShortExceptionClass(),
                'message' => $job->exception_message,
                'trace' => PayloadRedactor::redactTrace($job->exception_trace),
            ] : null,
            'retry_chain' => $retryChain,
        ]);
    }

    /**
     * Analytics tab: job class stats, queue stats, server stats, failure patterns, tag stats
     */
    public function analytics(): JsonResponse
    {
        $jobClasses = $this->statsRepository->getJobClassStatistics();
        $queues = $this->statsRepository->getQueueStatistics();
        $servers = $this->statsRepository->getServerStatistics();
        $failurePatterns = $this->statsRepository->getFailurePatterns();
        $tags = $this->tagRepository->getTagStatistics();

        return response()->json([
            'job_classes' => $jobClasses,
            'queues' => $queues,
            'servers' => $servers,
            'failure_patterns' => $failurePatterns,
            'tags' => $tags,
        ]);
    }

    /**
     * Get redacted payload for a specific job (legacy endpoint)
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
            'exception' => PayloadRedactor::redactTrace($job->exception_trace),
        ]);
    }
}
