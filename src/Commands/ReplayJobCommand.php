<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Commands;

use Illuminate\Console\Command;
use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;

class ReplayJobCommand extends Command
{
    public $signature = 'queue-monitor:replay {uuid : The UUID of the job to replay}';

    public $description = 'Replay a monitored job';

    public function handle(LaravelQueueMonitor $monitor): int
    {
        $uuid = $this->argument('uuid');

        if (! is_string($uuid)) {
            $this->error('Invalid UUID provided');

            return self::FAILURE;
        }

        $this->info("Replaying job {$uuid}...");

        try {
            $replayData = $monitor->replay($uuid);

            $this->info('Job replayed successfully!');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Original UUID', $replayData->originalUuid],
                    ['New UUID', $replayData->newUuid],
                    ['New Job ID', $replayData->newJobId ?? 'N/A'],
                    ['Queue', $replayData->queue],
                    ['Connection', $replayData->connection],
                    ['Replayed At', $replayData->replayedAt->toDateTimeString()],
                ]
            );

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error('Failed to replay job: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
