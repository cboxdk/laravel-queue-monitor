<?php

declare(strict_types=1);

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobQueuedAction;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Illuminate\Queue\Events\JobQueued;

beforeEach(function () {
    Carbon::setTestNow('2026-01-01 12:00:00');
    config()->set('queue.connections.sqs', ['driver' => 'sqs', 'queue' => 'default']);
    config()->set('queue.connections.redis.driver', 'redis');
});

afterEach(function () {
    Carbon::setTestNow();
});

function recordQueuedWithDelay(string $connection, string $queue, mixed $delay): ?JobMonitor
{
    app(RecordJobQueuedAction::class)->execute(new JobQueued($connection, $queue, 'job-1', new ExampleJob, '{}', $delay));

    return JobMonitor::first();
}

test('a delayed job on a standard SQS queue becomes available after the delay', function () {
    $monitor = recordQueuedWithDelay('sqs', 'emails', 60);

    expect($monitor?->available_at?->toDateTimeString())->toBe('2026-01-01 12:01:00');
});

test('a delayed job on an SQS FIFO queue is available immediately', function (mixed $delay) {
    $monitor = recordQueuedWithDelay('sqs', 'orders.fifo', $delay);

    expect($monitor?->available_at)->toBeNull();
})->with([
    'seconds' => [60],
    'date' => [fn () => now()->addMinutes(5)],
    'interval' => [fn () => new DateInterval('PT5M')],
]);

test('an SQS FIFO queue resolved from a URL default is available immediately', function () {
    config()->set('queue.connections.sqs.queue', 'https://sqs.eu-west-1.amazonaws.com/123456789012/orders.fifo');

    $monitor = recordQueuedWithDelay('sqs', '', 60);

    expect($monitor?->queue)->toBe('orders.fifo')
        ->and($monitor?->available_at)->toBeNull();
});

test('a delayed job on a non-SQS queue named like a FIFO queue keeps its delay', function () {
    $monitor = recordQueuedWithDelay('redis', 'orders.fifo', 60);

    expect($monitor?->available_at?->toDateTimeString())->toBe('2026-01-01 12:01:00');
});
