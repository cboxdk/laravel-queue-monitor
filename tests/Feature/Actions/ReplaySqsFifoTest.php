<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Actions\Batch\BatchReplayAction;
use Cbox\LaravelQueueMonitor\Actions\Replay\ReplayJobAction;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Testing\Fakes\QueueFake;

beforeEach(function () {
    config()->set('queue.connections.sqs', ['driver' => 'sqs', 'queue' => 'default']);
    config()->set('queue-monitor.storage.store_payload', true);
});

function failedJobOn(string $connection, string $queue): JobMonitor
{
    return JobMonitor::factory()->failed()->create([
        'connection' => $connection,
        'queue' => $queue,
        'payload' => [
            'displayName' => 'Test Job',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['command' => 'test'],
        ],
    ]);
}

/**
 * @return array{payload: string, queue: string|null, options: array<string, mixed>}
 */
function onlyRawPush(QueueFake $queue): array
{
    expect($queue->rawPushes())->toHaveCount(1);

    return $queue->rawPushes()[0];
}

test('replay to an SQS FIFO queue passes a message group and a fresh deduplication id', function () {
    $fake = Queue::fake();
    $job = failedJobOn('sqs', 'orders.fifo');

    app(ReplayJobAction::class)->execute($job->uuid);

    $push = onlyRawPush($fake);
    expect($push['queue'])->toBe('orders.fifo')
        ->and($push['options']['MessageGroupId'])->toBe('orders.fifo')
        ->and($push['options']['MessageDeduplicationId'])->toBeString()->not->toBeEmpty();
});

test('each replay of the same FIFO job gets its own deduplication id', function () {
    $fake = Queue::fake();
    $job = failedJobOn('sqs', 'orders.fifo');

    app(ReplayJobAction::class)->execute($job->uuid);
    app(ReplayJobAction::class)->execute($job->uuid);

    $ids = array_map(fn (array $push): mixed => $push['options']['MessageDeduplicationId'], $fake->rawPushes());
    expect(array_unique($ids))->toHaveCount(2);
});

test('replay of a row stored under a FIFO queue URL groups by the queue name', function () {
    config()->set('queue.connections.sqs.suffix', '-production');
    $fake = Queue::fake();
    $job = failedJobOn('sqs', 'https://sqs.eu-west-1.amazonaws.com/123456789012/orders-production.fifo');

    app(ReplayJobAction::class)->execute($job->uuid);

    expect(onlyRawPush($fake)['options']['MessageGroupId'])->toBe('orders.fifo');
});

test('replay to a standard SQS queue passes no options', function () {
    $fake = Queue::fake();
    $job = failedJobOn('sqs', 'emails');

    app(ReplayJobAction::class)->execute($job->uuid);

    expect(onlyRawPush($fake)['options'])->toBe([]);
});

test('replay to a non-SQS queue named like a FIFO queue passes no options', function () {
    config()->set('queue.connections.redis.driver', 'redis');
    $fake = Queue::fake();
    $job = failedJobOn('redis', 'orders.fifo');

    app(ReplayJobAction::class)->execute($job->uuid);

    expect(onlyRawPush($fake)['options'])->toBe([]);
});

test('batch replay to an SQS FIFO queue passes the FIFO options', function () {
    $fake = Queue::fake();
    $job = failedJobOn('sqs', 'orders.fifo');

    $result = app(BatchReplayAction::class)->executeByUuids([$job->uuid]);

    expect($result['success'])->toBe(1)
        ->and(onlyRawPush($fake)['options'])->toHaveKeys(['MessageGroupId', 'MessageDeduplicationId']);
});
