<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Support\ReleasedDatabaseJobs;
use Cbox\LaravelQueueMonitor\Tests\Support\BrokerJobs;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\Jobs\DatabaseJob;

function releasedDatabaseJob(int $id, string $uuid = 'payload-uuid-db'): DatabaseJob
{
    $record = (object) [
        'id' => $id,
        'queue' => 'default',
        'attempts' => 1,
        'payload' => json_encode(['uuid' => $uuid, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []], JSON_THROW_ON_ERROR),
    ];

    return new DatabaseJob(app(), Mockery::mock(DatabaseQueue::class), $record, 'database', 'default');
}

test('a database job waits under a marker built from its payload uuid', function () {
    expect((new ReleasedDatabaseJobs)->markerFor(releasedDatabaseJob(7)))->toBe('released:payload-uuid-db');
});

test('jobs that keep their id on release have no marker', function () {
    $released = new ReleasedDatabaseJobs;

    expect($released->markerFor(BrokerJobs::redis('redis-1', 1)))->toBeNull()
        ->and($released->markerFor(BrokerJobs::sqs('sqs-1', 1)))->toBeNull();
});

test('a database job without a payload uuid has no marker', function () {
    expect((new ReleasedDatabaseJobs)->markerFor(releasedDatabaseJob(7, '')))->toBeNull();
});

test('marking a release moves every row of the job, and only that job', function () {
    JobMonitor::factory()->count(2)->create(['job_id' => '7']);
    JobMonitor::factory()->create(['job_id' => '8']);

    (new ReleasedDatabaseJobs)->markReleased(releasedDatabaseJob(7));

    expect(JobMonitor::where('job_id', 'released:payload-uuid-db')->count())->toBe(2)
        ->and(JobMonitor::where('job_id', '8')->count())->toBe(1)
        ->and(JobMonitor::where('job_id', '7')->count())->toBe(0);
});

test('reclaiming moves the waiting rows to the new id', function () {
    JobMonitor::factory()->count(2)->create(['job_id' => 'released:payload-uuid-db']);

    $reclaimed = (new ReleasedDatabaseJobs)->reclaim(releasedDatabaseJob(9), '9');

    expect($reclaimed)->toBeTrue()
        ->and(JobMonitor::where('job_id', '9')->count())->toBe(2);
});

test('reclaiming with nothing waiting reports false', function () {
    expect((new ReleasedDatabaseJobs)->reclaim(releasedDatabaseJob(9), '9'))->toBeFalse()
        ->and((new ReleasedDatabaseJobs)->reclaim(BrokerJobs::redis('redis-1', 2), 'redis-1'))->toBeFalse();
});
