<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Cbox\LaravelQueueMonitor\Actions\ResolveStuckJobAction;
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;
use Illuminate\Console\Command;

class ResolveStuckJobsCommand extends Command
{
    public $signature = 'queue-monitor:resolve-stuck
                        {--minutes= : Minutes after which a processing job is considered stuck (defaults to retention.resolve_stuck_after_minutes)}
                        {--dry-run : List the jobs that would be resolved without changing them}';

    public $description = 'Mark jobs stuck in processing as timed out';

    public function handle(ResolveStuckJobAction $action): int
    {
        if (! config('queue-monitor.enabled', true)) {
            $this->info('Queue monitoring is disabled; nothing to resolve.');

            return self::SUCCESS;
        }

        $minutes = $this->resolveThresholdMinutes();

        if ($minutes === null) {
            $this->info('No stuck-job threshold configured (retention.resolve_stuck_after_minutes); skipping.');

            return self::SUCCESS;
        }

        /** @var list<string> $uuids */
        $uuids = QueryBuilderHelper::stuck($minutes)->pluck('uuid')->all();

        if ($uuids === []) {
            $this->info('No stuck jobs found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info(count($uuids)." stuck job(s) older than {$minutes} minute(s) would be marked as timed out.");

            return self::SUCCESS;
        }

        $result = $action->execute($uuids, 'timeout');

        $this->info("Marked {$result['resolved']} stuck job(s) as timed out.");

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }

    private function resolveThresholdMinutes(): ?int
    {
        $option = $this->option('minutes');

        if (is_numeric($option)) {
            return max(0, (int) $option);
        }

        $configured = config('queue-monitor.retention.resolve_stuck_after_minutes');

        return is_numeric($configured) ? max(0, (int) $configured) : null;
    }
}
