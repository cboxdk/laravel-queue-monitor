<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Enums\WorkerType;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

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
