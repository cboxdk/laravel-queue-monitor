<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Facades\Queue;

test('replay command replays a failed job', function () {
    Queue::fake();

    $job = JobMonitor::factory()->failed()->create();

    $this->artisan("queue-monitor:replay {$job->uuid}")
        ->expectsOutputToContain('Job replayed successfully')
        ->assertExitCode(0);
});

test('replay command fails for non-existent job', function () {
    $this->artisan('queue-monitor:replay nonexistent-uuid')
        ->expectsOutputToContain('Failed to replay job')
        ->assertExitCode(1);
});

test('replay command fails for non-replayable job', function () {
    $job = JobMonitor::factory()->processing()->create();

    $this->artisan("queue-monitor:replay {$job->uuid}")
        ->expectsOutputToContain('Failed to replay job')
        ->assertExitCode(1);
});
