<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\BrokerJobs;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;

/**
 * Parking a released attempt as `queued` decides on a superseded check and
 * then writes. A redelivery starting between the two would see the row
 * `processing`, close it and open its own, and the write would then park the
 * closed row as `queued` forever. The check and the write therefore run in
 * one transaction, under the row lock the started action takes.
 */
test('a released attempt is checked and parked inside one transaction', function () {
    event(new JobProcessing('redis', BrokerJobs::redis('locked-job', 1)));

    $outside = DB::transactionLevel();
    $levels = [];
    DB::listen(function (QueryExecuted $query) use (&$levels): void {
        if (str_contains($query->sql, 'queue_monitor_jobs')) {
            $levels[] = [strtok($query->sql, ' '), DB::transactionLevel()];
        }
    });

    event(new JobProcessed('redis', BrokerJobs::released(BrokerJobs::redis('locked-job', 1))));

    // The first query finds the attempt's row; everything after it — the
    // locked re-read, the superseded check and the update — is the parking.
    $parkingQueries = array_slice($levels, 1);

    expect(JobMonitor::firstOrFail()->status)->toBe(JobStatus::QUEUED)
        ->and(array_column($parkingQueries, 0))->toContain('update')
        ->and(array_unique(array_column($parkingQueries, 1)))->toBe([$outside + 1]);
});

test('a released attempt superseded before the lock is taken is left as it is', function () {
    event(new JobProcessing('redis', BrokerJobs::redis('locked-job', 1)));
    $this->travel(120)->seconds();
    event(new JobProcessing('redis', BrokerJobs::redis('locked-job', 2)));

    event(new JobProcessed('redis', BrokerJobs::released(BrokerJobs::redis('locked-job', 1))));

    $rows = JobMonitor::orderBy('id')->get();

    expect($rows->pluck('status')->all())->toBe([JobStatus::TIMEOUT, JobStatus::PROCESSING]);
});

test('a released attempt whose row was pruned meanwhile is ignored', function () {
    event(new JobProcessing('redis', BrokerJobs::redis('locked-job', 1)));
    $row = JobMonitor::firstOrFail();

    JobMonitor::query()->whereKey($row->id)->delete();
    event(new JobProcessed('redis', BrokerJobs::released(BrokerJobs::redis('locked-job', 1))));

    expect(JobMonitor::count())->toBe(0);
});

test('the lock is taken on the monitor connection when it is not the default', function () {
    config()->set('database.connections.monitor', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('queue-monitor.database.connection', 'monitor');
    (require __DIR__.'/../../../database/migrations/create_queue_monitor_jobs_table.php')->up();

    event(new JobProcessing('redis', BrokerJobs::redis('locked-job', 1)));

    $monitor = DB::connection('monitor');
    $levels = [];
    $monitor->listen(function (QueryExecuted $query) use (&$levels, $monitor): void {
        $levels[] = [strtok($query->sql, ' '), $monitor->transactionLevel()];
    });

    event(new JobProcessed('redis', BrokerJobs::released(BrokerJobs::redis('locked-job', 1))));

    $parkingQueries = array_slice($levels, 1);

    expect(JobMonitor::firstOrFail()->status)->toBe(JobStatus::QUEUED)
        ->and(JobMonitor::firstOrFail()->getConnectionName())->toBe('monitor')
        ->and(array_column($parkingQueries, 0))->toContain('update')
        ->and(array_unique(array_column($parkingQueries, 1)))->toBe([1]);
});

test('a start on the monitor connection runs its lookup and write in one transaction there', function () {
    config()->set('database.connections.monitor', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('queue-monitor.database.connection', 'monitor');
    (require __DIR__.'/../../../database/migrations/create_queue_monitor_jobs_table.php')->up();

    $monitor = DB::connection('monitor');
    $levels = [];
    $monitor->listen(function (QueryExecuted $query) use (&$levels, $monitor): void {
        $levels[] = [strtok($query->sql, ' '), $monitor->transactionLevel()];
    });

    event(new JobProcessing('redis', BrokerJobs::redis('locked-job', 1)));

    expect(JobMonitor::count())->toBe(1)
        ->and(array_column($levels, 0))->toContain('insert')
        ->and(collect($levels)->filter(fn (array $query): bool => $query[0] !== 'select')->pluck(1)->min())->toBeGreaterThanOrEqual(1);
});
