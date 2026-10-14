<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Support;

use Illuminate\Queue\Jobs\Job as QueueJob;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\RedisQueue;
use Mockery;

/**
 * Builds the framework's own Redis and SQS job objects the way their drivers
 * do on reserve, so attempts(), getJobId() and payload() run the real driver
 * code rather than a mock's canned answers.
 *
 * - Redis: the ready-queue payload carries the attempts made before this
 *   reservation; RedisJob::attempts() reports that + 1. The reserve script
 *   increments `attempts` in the reserved copy, which is what a retry_after
 *   expiry migrates back onto the queue — so a redelivery has the same id and
 *   one more attempt.
 * - SQS: the message keeps its MessageId across redeliveries and SQS counts
 *   receives in ApproximateReceiveCount, which SqsJob::attempts() reports.
 *   The AWS SDK is not a dependency of this package, so the job is built
 *   without its constructor (which only stores the SqsClient for delete and
 *   release calls these tests never make).
 */
final class BrokerJobs
{
    public static function redis(string $jobId, int $attempt, string $uuid = 'payload-uuid-redis'): RedisJob
    {
        $payload = self::payload($jobId, $uuid);

        $job = json_encode([...$payload, 'attempts' => $attempt - 1], JSON_THROW_ON_ERROR);
        $reserved = json_encode([...$payload, 'attempts' => $attempt], JSON_THROW_ON_ERROR);

        return new RedisJob(app(), Mockery::mock(RedisQueue::class), $job, $reserved, 'redis', 'default');
    }

    public static function sqs(string $messageId, int $attempt, string $uuid = 'payload-uuid-sqs'): SqsJob
    {
        $job = (new \ReflectionClass(SqsJob::class))->newInstanceWithoutConstructor();

        self::set($job, 'container', app());
        self::set($job, 'connectionName', 'sqs');
        self::set($job, 'queue', 'https://sqs.eu-west-1.amazonaws.com/123456789012/default');
        self::set($job, 'job', [
            'MessageId' => $messageId,
            'ReceiptHandle' => "receipt-{$attempt}",
            'Body' => json_encode(self::payload(null, $uuid), JSON_THROW_ON_ERROR),
            'Attributes' => ['ApproximateReceiveCount' => (string) $attempt],
        ]);

        return $job;
    }

    /**
     * Mark a job released the way Job::release() does, without the driver
     * call that would push it back onto a real broker.
     */
    public static function released(QueueJob $job): QueueJob
    {
        self::set($job, 'released', true);

        return $job;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(?string $jobId, string $uuid): array
    {
        return array_filter([
            'uuid' => $uuid,
            'id' => $jobId,
            'displayName' => 'Example Job for Testing',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => 5,
            'data' => [
                'commandName' => ExampleJob::class,
                'command' => serialize(new ExampleJob),
            ],
        ], fn (mixed $value): bool => $value !== null);
    }

    private static function set(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }
}
