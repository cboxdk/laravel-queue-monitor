<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\AlertingService;
use Cbox\LaravelQueueMonitor\Services\HealthCheckService;
use Cbox\LaravelQueueMonitor\Services\InfrastructureService;
use Cbox\LaravelQueueMonitor\Utilities\PayloadRedactor;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

use function Termwind\parse;
use function Termwind\render;

class QueueMonitorDashboardCommand extends Command
{
    public $signature = 'queue-monitor:dashboard
                            {--interval=2 : Refresh interval in seconds}
                            {--once : Run only once and exit (for CI/scripting)}';

    public $description = 'Interactive real-time queue monitoring dashboard';

    private int $selectedIndex = 0;

    private int $currentView = 1;

    private ?string $statusFilter = null;

    private string $searchQuery = '';

    private bool $inSearchMode = false;

    /** @var array<int, string> */
    private array $statusCycle = ['all', 'failed', 'processing', 'completed', 'queued', 'timeout'];

    private int $statusCycleIndex = 0;

    /** @var array<int, string> */
    private array $availableQueues = [];

    private int $queueCycleIndex = 0;

    private ?string $queueFilter = null;

    private int $pageOffset = 0;

    private int $perPage = 20;

    /** Job detail mode */
    private ?string $jobDetailUuid = null;

    private ?JobMonitor $jobDetailModel = null;

    /** Cached data for analytics/infrastructure (lazy-loaded) */
    /** @var array<string, mixed>|null */
    private ?array $analyticsData = null;

    /** @var array<string, mixed>|null */
    private ?array $infrastructureData = null;

    /** @var array<string, mixed>|null */
    private ?array $healthData = null;

    /** @var array<string, mixed> */
    private array $alertsData = [];

    /** Track when cached data was last fetched */
    private int $analyticsLastFetched = 0;

    private int $infraLastFetched = 0;

    private int $healthLastFetched = 0;

    private bool $interactive = false;

    public function handle(
        JobMonitorRepositoryContract $jobRepository,
        StatisticsRepositoryContract $statsRepository,
    ): int {
        $interval = (int) $this->option('interval');
        $once = (bool) $this->option('once');

        // Discover available queues for cycling
        /** @var array<int, string> $queues */
        $queues = JobMonitor::query()
            ->distinct()
            ->whereNotNull('queue')
            ->pluck('queue')
            ->sort()
            ->values()
            ->all();
        $this->availableQueues = $queues;

        if ($once) {
            $this->renderView($jobRepository, $statsRepository);

            return self::SUCCESS;
        }

        $this->interactive = true;

        // Enter alternate screen buffer + hide cursor + raw mode
        $this->output->write("\033[?1049h"); // Enter alternate screen
        $this->output->write("\033[?25l");   // Hide cursor
        system('stty -icanon -echo');

        try {
            $this->runInteractiveLoop($jobRepository, $statsRepository, $interval);
        } finally {
            // Restore everything: show cursor, exit alternate screen, restore terminal
            $this->output->write("\033[?25h");   // Show cursor
            $this->output->write("\033[?1049l"); // Exit alternate screen
            system('stty sane');
        }

        return self::SUCCESS;
    }

    private function runInteractiveLoop(
        JobMonitorRepositoryContract $jobRepository,
        StatisticsRepositoryContract $statsRepository,
        int $interval,
    ): void {
        $lastRender = 0;

        while (true) {
            $now = time();

            // Render at interval
            if ($now - $lastRender >= $interval) {
                $this->renderView($jobRepository, $statsRepository);
                $lastRender = $now;
            }

            // Non-blocking key read
            $key = $this->readKey();

            if ($key === null) {
                // Small sleep to avoid CPU spinning
                usleep(50000); // 50ms

                continue;
            }

            if ($this->inSearchMode) {
                $this->handleSearchInput($key);

                // Re-render immediately after search input
                $this->renderView($jobRepository, $statsRepository);
                $lastRender = time();

                continue;
            }

            $action = $this->handleKeyPress($key, $jobRepository);

            if ($action === 'quit') {
                break;
            }

            // Re-render immediately after key press
            $this->renderView($jobRepository, $statsRepository);
            $lastRender = time();
        }
    }

    private function readKey(): ?string
    {
        $stdin = STDIN;
        $read = [$stdin];
        $write = null;
        $except = null;

        // Non-blocking check with 0-second timeout
        if (stream_select($read, $write, $except, 0, 0) > 0) {
            $char = fread($stdin, 16);

            if ($char === false || $char === '') {
                return null;
            }

            return $char;
        }

        return null;
    }

    private function handleKeyPress(string $key, JobMonitorRepositoryContract $jobRepository): ?string
    {
        // Ctrl+C
        if ($key === "\x03") {
            return 'quit';
        }

        // If viewing job detail, handle differently
        if ($this->jobDetailUuid !== null) {
            return $this->handleJobDetailKeyPress($key, $jobRepository);
        }

        return match ($key) {
            'q', 'Q' => 'quit',
            'j', "\x1b[B" => $this->moveDown(),     // j or arrow down
            'k', "\x1b[A" => $this->moveUp(),       // k or arrow up
            "\n", "\r" => $this->openJobDetail($jobRepository),  // Enter
            'r', 'R' => $this->replaySelected($jobRepository),
            's', 'S' => $this->cycleStatusFilter(),
            'f', 'F' => $this->enterSearchMode(),
            'w', 'W' => $this->cycleQueueFilter(),
            'n', "\x1b[C" => $this->nextPage(),      // n or arrow right
            'p', "\x1b[D" => $this->prevPage(),      // p or arrow left
            '1' => $this->switchView(1),
            '2' => $this->switchView(2),
            '3' => $this->switchView(3),
            '4' => $this->switchView(4),
            '5' => $this->switchView(5),
            '6' => $this->switchView(6),
            default => null,
        };
    }

    private function handleJobDetailKeyPress(string $key, JobMonitorRepositoryContract $jobRepository): ?string
    {
        return match ($key) {
            'q', 'Q' => 'quit',
            "\x1b", "\x7f", 'b', 'B' => $this->closeJobDetail(),  // Escape, Backspace, b
            'r', 'R' => $this->replayJobDetail($jobRepository),
            default => null,
        };
    }

    private function handleSearchInput(string $key): void
    {
        // Escape or Enter exits search mode
        if ($key === "\x1b" || $key === "\n" || $key === "\r") {
            $this->inSearchMode = false;

            return;
        }

        // Backspace
        if ($key === "\x7f" || $key === "\x08") {
            $this->searchQuery = mb_substr($this->searchQuery, 0, -1);

            return;
        }

        // Only accept printable characters
        if (mb_strlen($key) === 1 && ord($key) >= 32) {
            $this->searchQuery .= $key;
        }
    }

    private function moveDown(): null
    {
        $this->selectedIndex++;

        return null;
    }

    private function moveUp(): null
    {
        $this->selectedIndex = max(0, $this->selectedIndex - 1);

        return null;
    }

    private function nextPage(): null
    {
        $this->pageOffset += $this->perPage;
        $this->selectedIndex = 0;

        return null;
    }

    private function prevPage(): null
    {
        $this->pageOffset = max(0, $this->pageOffset - $this->perPage);
        $this->selectedIndex = 0;

        return null;
    }

    private function openJobDetail(JobMonitorRepositoryContract $jobRepository): null
    {
        if ($this->currentView !== 1) {
            return null;
        }

        $jobs = $this->getFilteredJobs($jobRepository);
        $job = $jobs->values()->get($this->selectedIndex);

        if ($job !== null) {
            $this->jobDetailUuid = $job->uuid;
            $this->jobDetailModel = $job;
        }

        return null;
    }

    private function closeJobDetail(): null
    {
        $this->jobDetailUuid = null;
        $this->jobDetailModel = null;

        return null;
    }

    private function replayJobDetail(JobMonitorRepositoryContract $jobRepository): null
    {
        if ($this->jobDetailUuid === null) {
            return null;
        }

        system('stty sane');

        try {
            $job = $this->jobDetailModel;

            if ($job === null) {
                return null;
            }

            if ($this->confirm("Replay {$job->getShortJobClass()} (attempt #{$job->attempt})?")) {
                try {
                    /** @var \Cbox\LaravelQueueMonitor\LaravelQueueMonitor $monitor */
                    $monitor = app(\Cbox\LaravelQueueMonitor\LaravelQueueMonitor::class);
                    $result = $monitor->replay($job->uuid);
                    $this->info("Replayed → new job: {$result->newJobId}");
                    sleep(1);
                } catch (\Throwable $e) {
                    $this->error("Replay failed: {$e->getMessage()}");
                    sleep(2);
                }
            }
        } finally {
            system('stty -icanon -echo');
        }

        $this->closeJobDetail();

        return null;
    }

    private function replaySelected(JobMonitorRepositoryContract $jobRepository): null
    {
        system('stty sane');

        try {
            $jobs = $this->getFilteredJobs($jobRepository);
            $job = $jobs->values()->get($this->selectedIndex);

            if ($job === null) {
                $this->warn('No job selected.');

                return null;
            }

            if ($this->confirm("Replay {$job->getShortJobClass()} (attempt #{$job->attempt})?")) {
                try {
                    /** @var LaravelQueueMonitor $monitor */
                    $monitor = app(LaravelQueueMonitor::class);
                    $result = $monitor->replay($job->uuid);
                    $this->info("Replayed → new job: {$result->newJobId}");
                    sleep(1);
                } catch (\Throwable $e) {
                    $this->error("Replay failed: {$e->getMessage()}");
                    sleep(2);
                }
            }
        } finally {
            system('stty -icanon -echo');
        }

        return null;
    }

    private function cycleStatusFilter(): null
    {
        $this->statusCycleIndex = ($this->statusCycleIndex + 1) % count($this->statusCycle);
        $filter = $this->statusCycle[$this->statusCycleIndex];
        $this->statusFilter = $filter === 'all' ? null : $filter;
        $this->selectedIndex = 0;
        $this->pageOffset = 0;

        return null;
    }

    private function cycleQueueFilter(): null
    {
        if (empty($this->availableQueues)) {
            return null;
        }

        $this->queueCycleIndex = ($this->queueCycleIndex + 1) % (count($this->availableQueues) + 1);

        if ($this->queueCycleIndex === 0) {
            $this->queueFilter = null;
        } else {
            $this->queueFilter = $this->availableQueues[$this->queueCycleIndex - 1];
        }

        $this->selectedIndex = 0;
        $this->pageOffset = 0;

        return null;
    }

    private function enterSearchMode(): null
    {
        $this->inSearchMode = true;
        $this->searchQuery = '';

        return null;
    }

    private function switchView(int $view): null
    {
        $this->currentView = $view;
        $this->selectedIndex = 0;

        // Invalidate cached data when switching to force a fresh fetch
        if ($view === 5) {
            $this->analyticsData = null;
        }
        if ($view === 6) {
            $this->infrastructureData = null;
        }
        if ($view === 4) {
            $this->healthData = null;
        }

        return null;
    }

    private function renderView(
        JobMonitorRepositoryContract $jobRepository,
        StatisticsRepositoryContract $statsRepository,
    ): void {
        try {
            $this->doRender($jobRepository, $statsRepository);
        } catch (\Throwable $e) {
            $this->output->write("\033[H\033[J");
            $this->error("Dashboard error: {$e->getMessage()}");
            $this->line('Retrying in next interval...');
        }
    }

    private function doRender(
        JobMonitorRepositoryContract $jobRepository,
        StatisticsRepositoryContract $statsRepository,
    ): void {
        $globalStats = $statsRepository->getGlobalStatistics();
        $queueHealth = $statsRepository->getQueueHealth();
        $jobs = $this->getFilteredJobs($jobRepository);

        // Count total for pagination
        $totalJobs = $this->getTotalFilteredJobs();

        // Clamp selected index to valid range
        $maxIndex = max(0, $jobs->count() - 1);
        $this->selectedIndex = min($this->selectedIndex, $maxIndex);

        // Determine overall health
        $healthy = true;
        /** @var array<string, mixed> $queue */
        foreach ($queueHealth as $queue) {
            if (($queue['status'] ?? 'healthy') !== 'healthy') {
                $healthy = false;
                break;
            }
        }

        // Fetch alerts
        try {
            $alertingService = app(AlertingService::class);
            $this->alertsData = $alertingService->checkAlertConditions();
        } catch (\Throwable) {
            $this->alertsData = [];
        }

        // Lazy-load view-specific data
        $now = time();

        if ($this->currentView === 4 && ($this->healthData === null || $now - $this->healthLastFetched > 10)) {
            try {
                $healthService = app(HealthCheckService::class);
                $this->healthData = $healthService->check();
                $this->healthData['score'] = $healthService->getHealthScore();
                $this->healthLastFetched = $now;
            } catch (\Throwable) {
                $this->healthData = ['status' => 'unknown', 'checks' => [], 'score' => 0];
            }
        }

        if ($this->currentView === 5 && ($this->analyticsData === null || $now - $this->analyticsLastFetched > 15)) {
            try {
                $tagRepository = app(TagRepositoryContract::class);
                $this->analyticsData = [
                    'job_classes' => $statsRepository->getJobClassStatistics(),
                    'queues' => $statsRepository->getQueueStatistics(),
                    'servers' => $statsRepository->getServerStatistics(),
                    'failure_patterns' => $statsRepository->getFailurePatterns(),
                    'tags' => $tagRepository->getTagStatistics()->toArray(),
                ];
                $this->analyticsLastFetched = $now;
            } catch (\Throwable) {
                $this->analyticsData = ['job_classes' => [], 'queues' => [], 'servers' => [], 'failure_patterns' => [], 'tags' => []];
            }
        }

        if ($this->currentView === 6 && ($this->infrastructureData === null || $now - $this->infraLastFetched > 10)) {
            try {
                $infraService = app(InfrastructureService::class);
                $this->infrastructureData = [
                    'workers' => $infraService->getWorkerData(),
                    'worker_types' => $infraService->getWorkerTypeBreakdown(),
                    'sla' => $infraService->getSlaData(),
                    'scaling' => $infraService->getScalingData(),
                    'capacity' => $infraService->getCapacityData(),
                ];
                $this->infraLastFetched = $now;
            } catch (\Throwable) {
                $this->infrastructureData = ['workers' => ['available' => false], 'worker_types' => [], 'sla' => [], 'scaling' => [], 'capacity' => []];
            }
        }

        // Load job detail model if in detail mode
        $jobDetail = null;
        $jobRetryChain = collect();
        if ($this->jobDetailUuid !== null) {
            $jobDetail = $jobRepository->findByUuid($this->jobDetailUuid);
            if ($jobDetail !== null) {
                $this->jobDetailModel = $jobDetail;
                $jobRetryChain = $jobRepository->getRetryChain($this->jobDetailUuid);
            } else {
                $this->closeJobDetail();
            }
        }

        /** @var view-string $view */
        $view = 'queue-monitor::tui.dashboard';

        $html = view($view, [
            'stats' => $globalStats,
            'queues' => $queueHealth,
            'jobs' => $jobs,
            'totalJobs' => $totalJobs,
            'selectedIndex' => $this->selectedIndex,
            'currentView' => $this->currentView,
            'statusFilter' => $this->statusFilter,
            'queueFilter' => $this->queueFilter,
            'searchQuery' => $this->searchQuery,
            'inSearchMode' => $this->inSearchMode,
            'timestamp' => now()->format('H:i:s'),
            'healthy' => $healthy,
            'alerts' => $this->alertsData,
            'pageOffset' => $this->pageOffset,
            'perPage' => $this->perPage,
            // Job detail
            'jobDetail' => $jobDetail,
            'jobRetryChain' => $jobRetryChain,
            // Analytics
            'analyticsData' => $this->analyticsData,
            // Health
            'healthData' => $this->healthData,
            // Infrastructure
            'infrastructureData' => $this->infrastructureData,
        ])->render();

        if ($this->interactive) {
            // Parse to string (no side-effects) → cursor home → write → clear rest
            $rendered = parse($html);
            $this->output->write("\033[H" . $rendered . "\n\033[J");
        } else {
            // --once mode: just render inline
            render($html);
        }
    }

    /**
     * @return Collection<int, JobMonitor>
     */
    private function getFilteredJobs(JobMonitorRepositoryContract $jobRepository): Collection
    {
        $query = JobMonitor::query()->orderByDesc('queued_at')->offset($this->pageOffset)->limit($this->perPage);

        // Apply status filter
        if ($this->statusFilter !== null) {
            $statusEnum = match ($this->statusFilter) {
                'failed' => [JobStatus::FAILED, JobStatus::TIMEOUT],
                'processing' => [JobStatus::PROCESSING],
                'completed' => [JobStatus::COMPLETED],
                'queued' => [JobStatus::QUEUED],
                'timeout' => [JobStatus::TIMEOUT],
                default => [],
            };

            if (! empty($statusEnum)) {
                $query->whereIn('status', array_map(fn ($s) => $s->value, $statusEnum));
            }
        }

        // Apply queue filter
        if ($this->queueFilter !== null) {
            $query->where('queue', $this->queueFilter);
        }

        // Apply search filter
        if ($this->searchQuery !== '') {
            $search = $this->searchQuery;
            $query->where(function ($q) use ($search) {
                $q->where('job_class', 'like', "%{$search}%")
                    ->orWhere('queue', 'like', "%{$search}%")
                    ->orWhere('server_name', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    private function getTotalFilteredJobs(): int
    {
        $query = JobMonitor::query();

        if ($this->statusFilter !== null) {
            $statusEnum = match ($this->statusFilter) {
                'failed' => [JobStatus::FAILED, JobStatus::TIMEOUT],
                'processing' => [JobStatus::PROCESSING],
                'completed' => [JobStatus::COMPLETED],
                'queued' => [JobStatus::QUEUED],
                'timeout' => [JobStatus::TIMEOUT],
                default => [],
            };

            if (! empty($statusEnum)) {
                $query->whereIn('status', array_map(fn ($s) => $s->value, $statusEnum));
            }
        }

        if ($this->queueFilter !== null) {
            $query->where('queue', $this->queueFilter);
        }

        if ($this->searchQuery !== '') {
            $search = $this->searchQuery;
            $query->where(function ($q) use ($search) {
                $q->where('job_class', 'like', "%{$search}%")
                    ->orWhere('queue', 'like', "%{$search}%")
                    ->orWhere('server_name', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%");
            });
        }

        return $query->count();
    }
}
