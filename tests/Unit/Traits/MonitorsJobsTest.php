<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Traits\MonitorsJobs;

test('trait provides default display name', function () {
    $job = new class
    {
        use MonitorsJobs;
    };

    expect($job->displayName())->toBe(class_basename($job));
});

test('trait provides empty default tags', function () {
    $job = new class
    {
        use MonitorsJobs;
    };

    expect($job->tags())->toBe([]);
});

test('trait defaults shouldBeMonitored to true', function () {
    $job = new class
    {
        use MonitorsJobs;
    };

    expect($job->shouldBeMonitored())->toBeTrue();
});

test('trait defaults shouldStorePayload from config', function () {
    $job = new class
    {
        use MonitorsJobs;
    };

    config()->set('queue-monitor.storage.store_payload', true);
    expect($job->shouldStorePayload())->toBeTrue();

    config()->set('queue-monitor.storage.store_payload', false);
    expect($job->shouldStorePayload())->toBeFalse();
});
