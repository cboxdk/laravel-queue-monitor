<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use PHPeek\LaravelQueueMonitor\Enums\JobStatus;
use PHPeek\LaravelQueueMonitor\Enums\WorkerType;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;

test('prune command removes old jobs', function () {
    // Create old job and manually update created_at using query builder (bypasses Eloquent timestamp)
    $old = JobMonitor::create([
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

    // Update created_at directly using query builder to bypass Eloquent timestamp handling
    JobMonitor::where('id', $old->id)->update(['created_at' => now()->subDays(40)]);
    $old->refresh();

    $recent = JobMonitor::create([
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

    $this->artisan('queue-monitor:prune', ['--days' => 30])
        ->expectsOutputToContain('Pruned')
        ->assertSuccessful();

    expect(JobMonitor::find($old->id))->toBeNull();
    expect(JobMonitor::find($recent->id))->not->toBeNull();
});
