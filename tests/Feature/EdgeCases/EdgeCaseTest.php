<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Exceptions\JobReplayException;
use PHPeek\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

test('handles job with no payload gracefully', function () {
    $job = JobMonitor::factory()->create(['payload' => null]);

    expect(fn () => QueueMonitor::replay($job->uuid))
        ->toThrow(JobReplayException::class, 'payload not stored');
});

test('handles invalid uuid format', function () {
    expect(fn () => QueueMonitor::getJob('invalid-uuid'))
        ->not->toThrow(\Exception::class);

    expect(QueueMonitor::getJob('invalid-uuid'))->toBeNull();
});

test('handles job with very large payload', function () {
    $largePayload = array_fill(0, 1000, str_repeat('A', 100));

    $job = JobMonitor::factory()->create([
        'payload' => $largePayload,
    ]);

    expect($job->payload)->toBeArray();
    expect(count($job->payload))->toBe(1000);
});

test('handles job with null duration', function () {
    $job = JobMonitor::factory()->processing()->create([
        'duration_ms' => null,
    ]);

    expect($job->getDurationInSeconds())->toBeNull();
});

test('handles job with missing timestamps', function () {
    $job = JobMonitor::factory()->queued()->create([
        'started_at' => null,
        'completed_at' => null,
    ]);

    expect($job->started_at)->toBeNull();
    expect($job->completed_at)->toBeNull();
    expect($job->isFinished())->toBeFalse();
});

test('handles concurrent updates to same job', function () {
    $job = JobMonitor::factory()->processing()->create();

    // Simulate concurrent updates
    $job1 = JobMonitor::find($job->id);
    $job2 = JobMonitor::find($job->id);

    $job1->update(['duration_ms' => 1000]);
    $job2->update(['status' => JobStatus::COMPLETED]);

    $job->refresh();

    expect($job->status)->toBe(JobStatus::COMPLETED);
});

test('handles job with empty tags array', function () {
    $job = JobMonitor::factory()->withTags([])->create();

    expect($job->tags)->toBeArray();
    expect($job->tags)->toBeEmpty();
});

test('handles retry chain with circular reference prevention', function () {
    $job1 = JobMonitor::factory()->create();
    $job2 = JobMonitor::factory()->create([
        'retried_from_id' => $job1->id,
        'uuid' => $job1->uuid,
    ]);

    $chain = QueueMonitor::getRetryChain($job1->uuid);

    expect($chain)->toHaveCount(2);
    expect($chain->pluck('id')->unique())->toHaveCount(2);
});

test('handles job class that no longer exists', function () {
    $job = JobMonitor::factory()->create([
        'job_class' => 'App\\Jobs\\NonExistentJob',
    ]);

    expect(fn () => QueueMonitor::replay($job->uuid))
        ->toThrow(JobReplayException::class, 'no longer exists');
});

test('handles very long exception messages', function () {
    $longMessage = str_repeat('Error occurred. ', 1000);

    $job = JobMonitor::factory()->failed()->create([
        'exception_message' => $longMessage,
    ]);

    expect(strlen($job->exception_message))->toBeGreaterThan(10000);
    expect($job->getShortExceptionClass())->not->toBeNull();
});

test('handles job with all null metrics', function () {
    $job = JobMonitor::factory()->create([
        'cpu_time_ms' => null,
        'memory_peak_mb' => null,
        'file_descriptors' => null,
        'duration_ms' => null,
    ]);

    expect($job->getDurationInSeconds())->toBeNull();
    expect($job->cpu_time_ms)->toBeNull();
});

test('handles deleted parent in retry chain', function () {
    $parent = JobMonitor::factory()->create();
    $child = JobMonitor::factory()->create([
        'retried_from_id' => $parent->id,
        'uuid' => $parent->uuid,
    ]);

    $parent->delete();
    $child->refresh();

    // SQLite doesn't enforce foreign key ON DELETE SET NULL, so parent reference may remain
    // In production MySQL/PostgreSQL, foreign key would be set to null
    // The important thing is that accessing the parent relationship returns null
    expect($child->parent)->toBeNull();
});
