<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Support\QueueName;

it('reduces an SQS queue URL to the queue name', function (string $queue, string $expected) {
    expect(QueueName::normalize('sqs', $queue))->toBe($expected);
})->with([
    'standard queue' => ['https://sqs.us-east-1.amazonaws.com/123456789012/emails', 'emails'],
    'fifo queue' => ['https://sqs.us-east-1.amazonaws.com/123456789012/orders.fifo', 'orders.fifo'],
    'custom endpoint' => ['http://localhost:4566/000000000000/emails', 'emails'],
    'underscore host' => ['http://local_stack:4566/000000000000/emails', 'emails'],
    'uppercase scheme' => ['HTTPS://sqs.us-east-1.amazonaws.com/123456789012/emails', 'emails'],
]);

it('leaves queue names that are not URLs unchanged', function (string $queue) {
    expect(QueueName::normalize('redis', $queue))->toBe($queue);
})->with([
    'plain' => ['default'],
    'with colon' => ['reports:nightly'],
    'with slash' => ['tenant/emails'],
    'fifo name' => ['orders.fifo'],
]);

it('leaves a URL without a path unchanged', function () {
    expect(QueueName::normalize('sqs', 'https://sqs.us-east-1.amazonaws.com'))
        ->toBe('https://sqs.us-east-1.amazonaws.com');
});

it('removes the connection suffix that the SQS driver appends', function (string $queue, string $expected) {
    config(['queue.connections.sqs.suffix' => '-production']);

    expect(QueueName::normalize('sqs', $queue))->toBe($expected);
})->with([
    'standard queue' => ['https://sqs.us-east-1.amazonaws.com/123456789012/emails-production', 'emails'],
    'fifo queue' => ['https://sqs.us-east-1.amazonaws.com/123456789012/orders-production.fifo', 'orders.fifo'],
    'name without the suffix' => ['https://sqs.us-east-1.amazonaws.com/123456789012/emails', 'emails'],
    'name equal to the suffix' => ['https://sqs.us-east-1.amazonaws.com/123456789012/-production', '-production'],
]);

it('does not strip the suffix from a queue name that is not a URL', function () {
    config(['queue.connections.sqs.suffix' => '-production']);

    expect(QueueName::normalize('sqs', 'emails-production'))->toBe('emails-production');
});

it('only removes the suffix of the connection the job ran on', function () {
    config(['queue.connections.other.suffix' => '-production']);

    expect(QueueName::normalize('sqs', 'https://sqs.us-east-1.amazonaws.com/123456789012/emails-production'))
        ->toBe('emails-production');
});
