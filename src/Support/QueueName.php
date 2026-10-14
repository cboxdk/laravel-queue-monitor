<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Support;

use Illuminate\Support\Str;

/**
 * Resolves the queue name jobs are recorded under.
 *
 * Laravel's SQS driver hands workers the full queue URL as the job's queue
 * (`SqsJob::getQueue()`), and an SQS connection's configured default queue is
 * often a URL too, while dispatching, `queue:work --queue` and the JobQueued
 * event use the queue name. Reducing a URL to the name it was built from keeps
 * every row for a queue under one name.
 *
 * Mirrors `Cbox\LaravelQueueMetrics\Support\QueueName`, which is internal to
 * that package and therefore copied rather than depended on.
 */
final class QueueName
{
    public static function normalize(string $connection, string $queue): string
    {
        if (! self::isUrl($queue)) {
            return $queue;
        }

        $path = parse_url($queue, PHP_URL_PATH);
        $name = is_string($path) ? basename($path) : '';

        if ($name === '') {
            return $queue;
        }

        return self::withoutSuffix($name, config("queue.connections.{$connection}.suffix"));
    }

    /**
     * Whether the queue lives on an SQS FIFO queue. FIFO queues reject a
     * per-message delay and require a message group on every send.
     */
    public static function isSqsFifo(string $connection, string $queue): bool
    {
        return config("queue.connections.{$connection}.driver") === 'sqs'
            && str_ends_with($queue, '.fifo');
    }

    /**
     * A plain scheme check rather than FILTER_VALIDATE_URL, which rejects
     * hostnames containing underscores — common for Docker Compose service
     * names such as `http://local_stack:4566/...`.
     */
    private static function isUrl(string $queue): bool
    {
        return Str::startsWith(Str::lower($queue), ['https://', 'http://']);
    }

    /**
     * Inverts SqsQueue::suffixQueue(), which places the suffix before `.fifo`.
     */
    private static function withoutSuffix(string $name, mixed $suffix): string
    {
        if (! is_string($suffix) || $suffix === '') {
            return $name;
        }

        $fifo = str_ends_with($name, '.fifo') ? '.fifo' : '';
        $base = $fifo === '' ? $name : Str::beforeLast($name, '.fifo');

        if (! str_ends_with($base, $suffix) || $base === $suffix) {
            return $name;
        }

        return substr($base, 0, -strlen($suffix)).$fifo;
    }
}
