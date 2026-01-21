<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Illuminate\Console\Command;

use function Termwind\render;
use function Termwind\terminal;

class QueueMonitorDashboardCommand extends Command
{
    public $signature = 'queue-monitor:dashboard 
                            {--interval=2 : Refresh interval in seconds}
                            {--once : Run only once and exit}';

    public $description = 'Display a real-time dashboard of queue metrics';

    public function handle(
        JobMonitorRepositoryContract $jobRepository,
        StatisticsRepositoryContract $statsRepository
    ): int {
        $interval = (int) $this->option('interval');
        $once = (bool) $this->option('once');

        terminal()->clear();

        while (true) {
            $this->renderDashboard($jobRepository, $statsRepository);

            if ($once) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }

    private function renderDashboard(
        JobMonitorRepositoryContract $jobRepo,
        StatisticsRepositoryContract $statsRepo
    ): void {
        // Fetch Data
        $globalStats = $statsRepo->getGlobalStatistics();
        $queueHealth = $statsRepo->getQueueHealth();
        $recentJobs = $jobRepo->getRecentJobs(10);
        $failedJobs = $jobRepo->getFailedJobs(5);

        // Clear screen logic (ANSI)
        $this->output->write("\033[2J\033[;H");

        /** @var view-string $view */
        $view = 'queue-monitor::tui.dashboard';

        render(view($view, [
            'stats' => $globalStats,
            'queues' => $queueHealth,
            'recentJobs' => $recentJobs,
            'failedJobs' => $failedJobs,
            'timestamp' => now()->format('H:i:s'),
        ])->render());
    }
}
