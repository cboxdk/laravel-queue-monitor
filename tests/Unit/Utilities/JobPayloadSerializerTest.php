<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Cbox\LaravelQueueMonitor\Utilities\JobPayloadSerializer;

test('serialize creates proper payload structure', function () {
    $job = new ExampleJob('test data');

    $payload = JobPayloadSerializer::serialize($job);

    expect($payload)->toBeArray();
    expect($payload)->toHaveKeys(['displayName', 'job', 'maxTries', 'data']);
    expect($payload['maxTries'])->toBe(3);
    expect($payload['data'])->toHaveKey('command');
});

test('deserialize reconstructs job instance', function () {
    $original = new ExampleJob('original data');
    $payload = JobPayloadSerializer::serialize($original);

    $deserialized = JobPayloadSerializer::deserialize($payload);

    expect($deserialized)->toBeInstanceOf(ExampleJob::class);
    expect($deserialized->data)->toBe('original data');
});

test('deserialize returns null for invalid payload', function () {
    $invalidPayload = ['invalid' => 'structure'];

    $result = JobPayloadSerializer::deserialize($invalidPayload);

    expect($result)->toBeNull();
});

test('extractTags returns job tags', function () {
    $job = new ExampleJob;

    $tags = JobPayloadSerializer::extractTags($job);

    expect($tags)->toBe(['example', 'test']);
});

test('extractTags returns null when no tags method', function () {
    $job = new stdClass;

    $tags = JobPayloadSerializer::extractTags($job);

    expect($tags)->toBeNull();
});

test('extractTags filters empty tags', function () {
    $job = new class
    {
        public function tags(): array
        {
            return ['valid', '', 'another', ''];
        }
    };

    $tags = JobPayloadSerializer::extractTags($job);

    expect($tags)->toBe(['valid', 'another']);
});

test('getQueue returns custom queue', function () {
    $job = new ExampleJob;
    $job->onQueue('custom');

    $queue = JobPayloadSerializer::getQueue($job);

    expect($queue)->toBe('custom');
});

test('getQueue returns default when no queue set', function () {
    $job = new ExampleJob;

    $queue = JobPayloadSerializer::getQueue($job);

    expect($queue)->toBe('default');
});

test('exceedsSizeLimit detects large payloads', function () {
    config()->set('queue-monitor.storage.payload_max_size', 100);

    $small = ['data' => 'small'];
    $large = ['data' => str_repeat('A', 1000)];

    expect(JobPayloadSerializer::exceedsSizeLimit($small))->toBeFalse();
    expect(JobPayloadSerializer::exceedsSizeLimit($large))->toBeTrue();
});
