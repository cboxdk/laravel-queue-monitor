<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Support\JobKey;
use Illuminate\Contracts\Queue\Job;

it('uses the driver job id when it is non-empty', function (string|int $driverJobId, string $expected) {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getJobId')->andReturn($driverJobId);
    $job->shouldNotReceive('uuid');

    expect(JobKey::of($job))->toBe($expected);
})->with([
    'redis string id' => ['a1b2c3', 'a1b2c3'],
    'database integer id' => [12345, '12345'],
]);

it('falls back to the payload uuid when the driver job id is empty', function () {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getJobId')->andReturn('');
    $job->shouldReceive('uuid')->andReturn('5f0b7c1e-2d6a-4a8e-9c3b-1e2f3a4b5c6d');

    expect(JobKey::of($job))->toBe('5f0b7c1e-2d6a-4a8e-9c3b-1e2f3a4b5c6d');
});

it('falls back to the object id when neither a job id nor a uuid exists', function (?string $uuid) {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getJobId')->andReturn('');
    $job->shouldReceive('uuid')->andReturn($uuid);

    expect(JobKey::of($job))->toBe('object-'.spl_object_id($job));
})->with([
    'null uuid' => [null],
    'empty uuid' => [''],
]);

it('gives two sync jobs with distinct payloads distinct keys', function () {
    $outer = Mockery::mock(Job::class);
    $outer->shouldReceive('getJobId')->andReturn('');
    $outer->shouldReceive('uuid')->andReturn('outer-uuid');

    $inner = Mockery::mock(Job::class);
    $inner->shouldReceive('getJobId')->andReturn('');
    $inner->shouldReceive('uuid')->andReturn('inner-uuid');

    expect(JobKey::of($outer))->not->toBe(JobKey::of($inner));
});
