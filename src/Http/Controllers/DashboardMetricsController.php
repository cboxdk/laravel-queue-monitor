<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Http\Controllers;

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Http\Transformers\JobMonitorTransformer;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\AlertingService;
use Cbox\LaravelQueueMonitor\Services\DashboardCacheService;
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
        private readonly DashboardCacheService $dashboardCache,
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
            ->map(fn ($job) => array_merge(
                JobMonitorTransformer::toListArray($job),
                ['queued_at' => $job->queued_at->diffForHumans()],
            ));

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

        $data = $jobs->map(fn ($job) => JobMonitorTransformer::toListArray($job));

        // Provide distinct queue names for the filter dropdown (cached to avoid full table scan)
        $availableQueues = Cache::remember($this->dashboardCache->scopedKey('available_queues'), 60, fn () => JobMonitor::query()
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
            ->map(fn ($retryJob) => JobMonitorTransformer::toRetryChainArray($retryJob, $uuid));

        return response()->json([
            'job' => JobMonitorTransformer::toDetailArray($job, $sensitiveKeys),
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
