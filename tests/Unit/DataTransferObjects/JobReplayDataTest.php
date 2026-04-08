<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobReplayData;

test('can create from constructor', function () {
    $data = new JobReplayData(
        originalUuid: 'orig-uuid',
        newUuid: 'new-uuid',
        newJobId: 'job-123',
        queue: 'default',
        connection: 'redis',
        replayedAt: now(),
    );

    expect($data->originalUuid)->toBe('orig-uuid');
    expect($data->newUuid)->toBe('new-uuid');
    expect($data->newJobId)->toBe('job-123');
});

test('can create from array', function () {
    $data = JobReplayData::fromArray([
        'original_uuid' => 'orig',
        'new_uuid' => 'new',
        'new_job_id' => 'job-1',
        'queue' => 'emails',
        'connection' => 'redis',
        'replayed_at' => '2025-01-01 12:00:00',
    ]);

    expect($data->originalUuid)->toBe('orig');
    expect($data->newUuid)->toBe('new');
    expect($data->queue)->toBe('emails');
    expect($data->connection)->toBe('redis');
});

test('fromArray handles missing new_job_id', function () {
    $data = JobReplayData::fromArray([
        'original_uuid' => 'orig',
        'new_uuid' => 'new',
        'queue' => 'default',
        'connection' => 'redis',
    ]);

    expect($data->newJobId)->toBeNull();
});

test('toArray returns correct structure', function () {
    $data = new JobReplayData(
        originalUuid: 'orig',
        newUuid: 'new',
        newJobId: 'job-1',
        queue: 'default',
        connection: 'redis',
        replayedAt: now(),
    );

    $array = $data->toArray();

    expect($array)->toHaveKeys([
        'original_uuid', 'new_uuid', 'new_job_id',
        'queue', 'connection', 'replayed_at',
    ]);
    expect($array['original_uuid'])->toBe('orig');
});
