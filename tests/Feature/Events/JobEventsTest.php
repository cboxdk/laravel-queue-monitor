<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Cbox\LaravelQueueMonitor\Events\JobCancelled;
use Cbox\LaravelQueueMonitor\Events\JobReplayRequested;
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

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

    // Factory uses ExampleJob::class which exists and has proper payload
    $job = JobMonitor::factory()->failed()->create([
        'payload' => [
            'displayName' => 'Test Job',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['command' => 'test'],
        ],
    ]);

    // Call through HTTP controller to trigger event dispatch
    $this->postJson("/api/queue-monitor/jobs/{$job->uuid}/replay")
        ->assertSuccessful();

    Event::assertDispatched(JobReplayRequested::class, function ($event) use ($job) {
        return $event->originalJob->uuid === $job->uuid
            && $event->replayData->originalUuid === $job->uuid;
    });
});
