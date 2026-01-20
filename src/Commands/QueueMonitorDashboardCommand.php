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
                        {--interval=2 : Refresh interval in seconds}';

    public $description = 'Display a real-time dashboard of queue metrics';

    public function handle(
        JobMonitorRepositoryContract $jobRepository,
        StatisticsRepositoryContract $statsRepository
    ): int {
        $interval = (int) $this->option('interval');

        terminal()->clear();

        // @phpstan-ignore-next-line
        while (true) {
            $this->renderDashboard($jobRepository, $statsRepository);

            sleep($interval);
        }
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

        render(view('queue-monitor::tui.dashboard', [
            'stats' => $globalStats,
            'queues' => $queueHealth,
            'recentJobs' => $recentJobs,
            'failedJobs' => $failedJobs,
            'timestamp' => now()->format('H:i:s'),
        ])->render());
    }
}
