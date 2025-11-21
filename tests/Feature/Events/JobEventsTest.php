<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPeek\LaravelQueueMonitor\Events\JobCancelled;
use PHPeek\LaravelQueueMonitor\Events\JobReplayRequested;
use PHPeek\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

test('cancel job fires JobCancelled event', function () {
    Event::fake([JobCancelled::class]);

    $job = JobMonitor::factory()->processing()->create();

    QueueMonitor::cancel($job->uuid);

    Event::assertDispatched(JobCancelled::class, function ($event) use ($job) {
        return $event->jobMonitor->uuid === $job->uuid;
    });
});

test('replay job fires JobReplayRequested event', function () {
    Event::fake([JobReplayRequested::class]);
    Queue::fake();

    $job = JobMonitor::factory()->failed()->create();

    QueueMonitor::replay($job->uuid);

    Event::assertDispatched(JobReplayRequested::class, function ($event) use ($job) {
        return $event->originalJob->uuid === $job->uuid
            && $event->replayData->originalUuid === $job->uuid;
    });
});
