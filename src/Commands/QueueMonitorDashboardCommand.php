<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Illuminate\Console\Command;

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
    private array $statusCycle = ['all', 'failed', 'processing', 'completed'];

    private int $statusCycleIndex = 0;

    public function handle(
        JobMonitorRepositoryContract $jobRepository,
        StatisticsRepositoryContract $statsRepository,
    ): int {
        $interval = (int) $this->option('interval');
        $once = (bool) $this->option('once');

        if ($once) {
            $this->renderView($jobRepository, $statsRepository);

            return self::SUCCESS;
        }

        // Enter raw terminal mode for interactive key reading
        system('stty -icanon -echo');

        try {
            $this->runInteractiveLoop($jobRepository, $statsRepository, $interval);
        } finally {
            // Always restore terminal to sane state
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

        return match ($key) {
            'q', 'Q' => 'quit',
            'j', "\x1b[B" => $this->moveDown(),     // j or arrow down
            'k', "\x1b[A" => $this->moveUp(),       // k or arrow up
            'r', 'R' => $this->replaySelected($jobRepository),
            's', 'S' => $this->cycleStatusFilter(),
            'f', 'F' => $this->enterSearchMode(),
            '1' => $this->switchView(1),
            '2' => $this->switchView(2),
            '3' => $this->switchView(3),
            '4' => $this->switchView(4),
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

        return null;
    }

    private function cycleStatusFilter(): null
    {
        $this->statusCycleIndex = ($this->statusCycleIndex + 1) % count($this->statusCycle);
        $filter = $this->statusCycle[$this->statusCycleIndex];
        $this->statusFilter = $filter === 'all' ? null : $filter;
        $this->selectedIndex = 0;

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

        return null;
    }

    private function renderView(
        JobMonitorRepositoryContract $jobRepository,
        StatisticsRepositoryContract $statsRepository,
    ): void {
        try {
            $this->doRender($jobRepository, $statsRepository);
        } catch (\Throwable $e) {
            $this->output->write("\033[2J\033[H");
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

        // Clear screen (ANSI)
        $this->output->write("\033[2J\033[H");

        /** @var view-string $view */
        $view = 'queue-monitor::tui.dashboard';

        render(view($view, [
            'stats' => $globalStats,
            'queues' => $queueHealth,
            'jobs' => $jobs,
            'selectedIndex' => $this->selectedIndex,
            'currentView' => $this->currentView,
            'statusFilter' => $this->statusFilter,
            'searchQuery' => $this->searchQuery,
            'inSearchMode' => $this->inSearchMode,
            'timestamp' => now()->format('H:i:s'),
            'healthy' => $healthy,
        ])->render());
    }

    /**
     * @return \Illuminate\Support\Collection<int, JobMonitor>
     */
    private function getFilteredJobs(JobMonitorRepositoryContract $jobRepository): \Illuminate\Support\Collection
    {
        $query = JobMonitor::query()->orderByDesc('queued_at')->limit(20);

        // Apply status filter
        if ($this->statusFilter !== null) {
            $statusEnum = match ($this->statusFilter) {
                'failed' => [JobStatus::FAILED, JobStatus::TIMEOUT],
                'processing' => [JobStatus::PROCESSING],
                'completed' => [JobStatus::COMPLETED],
                default => [],
            };

            if (! empty($statusEnum)) {
                $query->whereIn('status', array_map(fn ($s) => $s->value, $statusEnum));
            }
        }

        // Apply search filter
        if ($this->searchQuery !== '') {
            $search = $this->searchQuery;
            $query->where(function ($q) use ($search) {
                $q->where('job_class', 'like', "%{$search}%")
                    ->orWhere('queue', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }
}
