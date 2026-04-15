<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Illuminate\Support\Str;

test('prune command removes old jobs', function () {
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

test('prune command accepts max-rows option', function () {
    config()->set('queue-monitor.retention.max_rows', null);

    JobMonitor::factory()->count(10)->create([
        'status' => JobStatus::COMPLETED,
    ]);

    $this->artisan('queue-monitor:prune', ['--days' => 0, '--max-rows' => 3])
        ->expectsOutputToContain('Pruned')
        ->assertSuccessful();

    expect(JobMonitor::count())->toBe(3);
});
