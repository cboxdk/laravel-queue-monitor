<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\Actions\Core\PruneEventsAction;
use Cbox\LaravelQueueMonitor\Actions\Core\PruneJobsAction;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Illuminate\Console\Command;

class PruneJobsCommand extends Command
{
    public $signature = 'queue-monitor:prune
                        {--days= : Number of days to retain}
                        {--max-rows= : Maximum rows to keep (deletes oldest first)}
                        {--statuses=* : Job statuses to prune}';

    public $description = 'Prune old queue monitor job records';

    public function handle(PruneJobsAction $action): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $maxRows = $this->option('max-rows') !== null ? (int) $this->option('max-rows') : null;
        /** @var array<string> $rawStatuses */
        $rawStatuses = $this->option('statuses');

        /** @var array<JobStatus>|null $statuses */
        $statuses = null;

        if (! empty($rawStatuses)) {
            $statuses = array_map(
                fn (string $s): JobStatus => JobStatus::from($s),
                $rawStatuses
            );
        }

        $this->info('Pruning queue monitor jobs...');

        $deleted = $action->execute($days, $statuses, $maxRows);

        $this->info("Pruned {$deleted} job record(s).");

        // Prune scaling and cluster events
        $eventsAction = app(PruneEventsAction::class);
        $eventResult = $eventsAction->execute();

        $this->info("Pruned {$eventResult['scaling_events_deleted']} scaling event(s).");
        $this->info("Pruned {$eventResult['cluster_events_deleted']} cluster event(s).");

        if ($eventResult['payloads_pruned'] > 0) {
            $this->info("Cleared {$eventResult['payloads_pruned']} stale cluster event payload(s).");
        }

        return self::SUCCESS;
    }
}
