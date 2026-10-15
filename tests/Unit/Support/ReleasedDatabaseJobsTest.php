<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Support\ReleasedDatabaseJobs;
use Cbox\LaravelQueueMonitor\Tests\Support\BrokerJobs;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\Jobs\DatabaseJob;

function trackedDatabaseJob(int $id, int $attempts, string $uuid = 'payload-uuid-db'): DatabaseJob
{
    $record = (object) [
        'id' => $id,
        'queue' => 'default',
        'attempts' => $attempts,
        'payload' => json_encode(['uuid' => $uuid, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => []], JSON_THROW_ON_ERROR),
    ];

    return new DatabaseJob(app(), Mockery::mock(DatabaseQueue::class), $record, 'database', 'default');
}

test('only database jobs with a payload uuid are tracked', function () {
    $released = new ReleasedDatabaseJobs;

    expect($released->tracks(trackedDatabaseJob(7, 1)))->toBeTrue()
        ->and($released->tracks(trackedDatabaseJob(7, 1, '')))->toBeFalse()
        ->and($released->tracks(BrokerJobs::redis('redis-1', 1)))->toBeFalse()
        ->and($released->tracks(BrokerJobs::sqs('sqs-1', 1)))->toBeFalse();
});

test('a later delivery adopts a parked row and moves only that row', function () {
    $parked = JobMonitor::factory()->create(['job_id' => '7', 'attempt' => 1, 'status' => JobStatus::QUEUED]);
    $sibling = JobMonitor::factory()->create(['job_id' => '7', 'attempt' => 1, 'status' => JobStatus::TIMEOUT]);
    $released = new ReleasedDatabaseJobs;

    $released->recordRelease(trackedDatabaseJob(7, 1), $parked);
    $adopted = $released->adopt(trackedDatabaseJob(9, 2), '9');

    expect($adopted?->id)->toBe($parked->id)
        ->and($parked->refresh()->job_id)->toBe('9')
        ->and($sibling->refresh()->job_id)->toBe('7')
        ->and($released->movedRow('database', '7')?->id)->toBe($parked->id);
});

test('a failed released row is returned to link to, and stays where it is', function () {
    $failed = JobMonitor::factory()->create(['job_id' => '7', 'attempt' => 1, 'status' => JobStatus::FAILED]);
    $released = new ReleasedDatabaseJobs;

    $released->recordRelease(trackedDatabaseJob(7, 1), $failed);

    expect($released->adopt(trackedDatabaseJob(9, 2), '9')?->id)->toBe($failed->id)
        ->and($failed->refresh()->job_id)->toBe('7');
});

test('a release is adopted once', function () {
    $parked = JobMonitor::factory()->create(['job_id' => '7', 'attempt' => 1, 'status' => JobStatus::QUEUED]);
    $released = new ReleasedDatabaseJobs;

    $released->recordRelease(trackedDatabaseJob(7, 1), $parked);
    $released->adopt(trackedDatabaseJob(9, 2), '9');

    expect($released->adopt(trackedDatabaseJob(11, 2), '11'))->toBeNull();
});

test('a first attempt, or a release from a later attempt, is not adopted', function () {
    $parked = JobMonitor::factory()->create(['job_id' => '7', 'attempt' => 3, 'status' => JobStatus::QUEUED]);
    $released = new ReleasedDatabaseJobs;

    $released->recordRelease(trackedDatabaseJob(7, 3), $parked);

    expect($released->adopt(trackedDatabaseJob(9, 1), '9'))->toBeNull()
        ->and($released->adopt(trackedDatabaseJob(9, 3), '9'))->toBeNull()
        ->and($parked->refresh()->job_id)->toBe('7');
});

test('nothing is adopted without a recorded release', function () {
    expect((new ReleasedDatabaseJobs)->adopt(trackedDatabaseJob(9, 2), '9'))->toBeNull();
});

test('a delivery recorded first is connected when the parked release is recorded', function () {
    $parked = JobMonitor::factory()->create(['job_id' => '7', 'attempt' => 1, 'status' => JobStatus::QUEUED, 'retried_from_id' => null]);
    $delivery = JobMonitor::factory()->create(['job_id' => '9', 'attempt' => 2, 'status' => JobStatus::PROCESSING]);
    $released = new ReleasedDatabaseJobs;

    $released->recordRedelivery(trackedDatabaseJob(9, 2), $delivery);
    $released->recordRelease(trackedDatabaseJob(7, 1), $parked);

    expect(JobMonitor::find($parked->id))->toBeNull()
        ->and(JobMonitor::sole()->id)->toBe($delivery->id);
});

test('connecting is idempotent when both sides see each other', function () {
    $failed = JobMonitor::factory()->create(['job_id' => '7', 'attempt' => 1, 'status' => JobStatus::FAILED]);
    $delivery = JobMonitor::factory()->create(['job_id' => '9', 'attempt' => 2, 'status' => JobStatus::PROCESSING, 'retried_from_id' => null]);
    $released = new ReleasedDatabaseJobs;

    $released->recordRelease(trackedDatabaseJob(7, 1), $failed);
    $released->recordRedelivery(trackedDatabaseJob(9, 2), $delivery);
    $released->recordRelease(trackedDatabaseJob(7, 1), $failed);

    expect($delivery->refresh()->retried_from_id)->toBe($failed->id)
        ->and(JobMonitor::count())->toBe(2);
});

test('a delivery is never connected to itself or to a later attempt', function () {
    $row = JobMonitor::factory()->create(['job_id' => '9', 'attempt' => 2, 'status' => JobStatus::QUEUED]);
    $released = new ReleasedDatabaseJobs;

    $released->recordRedelivery(trackedDatabaseJob(9, 2), $row);
    $released->recordRelease(trackedDatabaseJob(9, 2), $row);

    expect(JobMonitor::find($row->id))->not->toBeNull();
});

test('an unknown moved id finds nothing', function () {
    expect((new ReleasedDatabaseJobs)->movedRow('database', '404'))->toBeNull();
});
