<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Commands;

use Illuminate\Console\Command;
use PHPeek\LaravelQueueMonitor\Actions\Core\PruneJobsAction;

class PruneJobsCommand extends Command
{
    public $signature = 'queue-monitor:prune
                        {--days= : Number of days to retain}
                        {--statuses=* : Job statuses to prune}';

    public $description = 'Prune old queue monitor job records';

    public function handle(PruneJobsAction $action): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $statuses = $this->option('statuses');

        if (! empty($statuses)) {
            $statuses = is_array($statuses) ? $statuses : [$statuses];
        } else {
            $statuses = null;
        }

        $this->info('Pruning queue monitor jobs...');

        $deleted = $action->execute($days, $statuses);

        $this->info("Pruned {$deleted} job record(s).");

        return self::SUCCESS;
    }
}
