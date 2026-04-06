<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Str;

test('stats command outputs valid JSON with --json flag', function () {
    JobMonitor::factory()->count(3)->create();
    JobMonitor::factory()->failed()->create();

    $this->artisan('queue-monitor:stats', ['--json' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"total"');
});

test('stats command json output does not contain table headers', function () {
    JobMonitor::factory()->create();

    $this->artisan('queue-monitor:stats', ['--json' => true])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('Queue Monitor Statistics');
});

test('stats command displays statistics', function () {
    JobMonitor::create([
        'uuid' => Str::uuid()->toString(),
        'job_class' => 'App\\Jobs\\TestJob',
        'connection' => 'redis',
        'queue' => 'default',
        'status' => JobStatus::COMPLETED,
        'attempt' => 1,
        'max_attempts' => 3,
        'server_name' => 'web-1',
        'worker_id' => 'worker-123',
        'worker_type' => WorkerType::QUEUE_WORK,
        'queued_at' => now(),
    ]);

    $this->artisan('queue-monitor:stats')
        ->expectsOutputToContain('Queue Monitor Statistics')
        ->expectsOutputToContain('Total Jobs')
        ->expectsOutputToContain('Completed')
        ->assertSuccessful();
});
