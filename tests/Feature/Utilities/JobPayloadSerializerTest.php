<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Tests\Support\ExampleJob;
use PHPeek\LaravelQueueMonitor\Utilities\JobPayloadSerializer;

test('serializes job instance to payload', function () {
    $job = new ExampleJob('test data');

    $payload = JobPayloadSerializer::serialize($job);

    expect($payload)->toHaveKeys(['displayName', 'job', 'data']);
    expect($payload['displayName'])->toBe('Example Job for Testing');
    expect($payload['data']['commandName'])->toBe(ExampleJob::class);
    expect($payload['data']['command'])->toContain('ExampleJob');
});

test('deserializes payload to job instance', function () {
    $originalJob = new ExampleJob('test data');
    $payload = JobPayloadSerializer::serialize($originalJob);

    $deserializedJob = JobPayloadSerializer::deserialize($payload);

    expect($deserializedJob)->toBeInstanceOf(ExampleJob::class);
    expect($deserializedJob->data)->toBe('test data');
});

test('extracts tags from job', function () {
    $job = new ExampleJob;

    $tags = JobPayloadSerializer::extractTags($job);

    expect($tags)->toBe(['example', 'test']);
});

test('returns null for job without tags method', function () {
    $job = new stdClass;

    $tags = JobPayloadSerializer::extractTags($job);

    expect($tags)->toBeNull();
});

test('gets queue name from job', function () {
    $job = new ExampleJob;
    $job->onQueue('emails');

    $queue = JobPayloadSerializer::getQueue($job);

    expect($queue)->toBe('emails');
});

test('checks if payload exceeds size limit', function () {
    config()->set('queue-monitor.storage.payload_max_size', 100);

    $smallPayload = ['data' => 'small'];
    $largePayload = ['data' => str_repeat('A', 1000)];

    expect(JobPayloadSerializer::exceedsSizeLimit($smallPayload))->toBeFalse();
    expect(JobPayloadSerializer::exceedsSizeLimit($largePayload))->toBeTrue();
});
