<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use PHPeek\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

beforeEach(function () {
    $this->action = app(ReplayJobAction::class);
});

test('can replay failed job', function () {
    Queue::fake();

    $job = JobMonitor::factory()->failed()->create([
        'payload' => [
            'displayName' => 'Test Job',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['command' => 'test'],
        ],
    ]);

    $replayData = $this->action->execute($job->uuid);

    expect($replayData->originalUuid)->toBe($job->uuid);
    expect($replayData->queue)->toBe($job->queue);
    expect($replayData->connection)->toBe($job->connection);

    Queue::assertPushedOn($job->queue);
});

test('throws exception when replaying processing job', function () {
    $job = JobMonitor::factory()->processing()->create();

    $this->action->execute($job->uuid);
})->throws(RuntimeException::class, 'Cannot replay job that is currently processing');

test('throws exception when job not found', function () {
    $this->action->execute('invalid-uuid');
})->throws(RuntimeException::class, 'not found');

test('throws exception when payload not stored', function () {
    $job = JobMonitor::factory()->failed()->create([
        'payload' => null,
    ]);

    $this->action->execute($job->uuid);
})->throws(RuntimeException::class, 'payload not stored');
