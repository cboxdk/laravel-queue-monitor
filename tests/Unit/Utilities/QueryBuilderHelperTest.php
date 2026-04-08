<?php

declare(strict_types=1);

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

test('lastHours returns jobs within the time window', function () {
    JobMonitor::factory()->create(['queued_at' => now()->subHours(2)]);
    JobMonitor::factory()->create(['queued_at' => now()->subHours(48)]);

    expect(QueryBuilderHelper::lastHours(24)->count())->toBe(1);
    expect(QueryBuilderHelper::lastHours(72)->count())->toBe(2);
});

test('today returns jobs from today only', function () {
    JobMonitor::factory()->create(['queued_at' => now()]);
    JobMonitor::factory()->create(['queued_at' => now()->subDays(2)]);

    expect(QueryBuilderHelper::today()->count())->toBe(1);
});

test('slow returns jobs above duration threshold', function () {
    JobMonitor::factory()->create(['duration_ms' => 10000]);
    JobMonitor::factory()->create(['duration_ms' => 1000]);
    JobMonitor::factory()->create(['duration_ms' => null]);

    expect(QueryBuilderHelper::slow(5000)->count())->toBe(1);
});

test('memoryIntensive returns jobs above memory threshold', function () {
    JobMonitor::factory()->create(['memory_peak_mb' => 200]);
    JobMonitor::factory()->create(['memory_peak_mb' => 50]);

    expect(QueryBuilderHelper::memoryIntensive(100)->count())->toBe(1);
});

test('failedWith returns jobs with specific exception class', function () {
    JobMonitor::factory()->failed()->create(['exception_class' => 'RuntimeException']);
    JobMonitor::factory()->failed()->create(['exception_class' => 'LogicException']);

    expect(QueryBuilderHelper::failedWith('RuntimeException')->count())->toBe(1);
});

test('retried returns jobs with parent', function () {
    $parent = JobMonitor::factory()->create();
    JobMonitor::factory()->create(['retried_from_id' => $parent->id]);

    expect(QueryBuilderHelper::retried()->count())->toBe(1);
});

test('withRetries returns jobs that have children', function () {
    $parent = JobMonitor::factory()->create();
    JobMonitor::factory()->create(['retried_from_id' => $parent->id]);

    expect(QueryBuilderHelper::withRetries()->count())->toBe(1);
});

test('byServer returns jobs for specific server', function () {
    JobMonitor::factory()->create(['server_name' => 'prod-01']);
    JobMonitor::factory()->create(['server_name' => 'prod-02']);

    expect(QueryBuilderHelper::byServer('prod-01')->count())->toBe(1);
});

test('horizonOnly returns only horizon worker jobs', function () {
    JobMonitor::factory()->create(['worker_type' => WorkerType::HORIZON]);
    JobMonitor::factory()->create(['worker_type' => WorkerType::QUEUE_WORK]);

    expect(QueryBuilderHelper::horizonOnly()->count())->toBe(1);
});

test('queueWorkOnly returns only queue:work jobs', function () {
    JobMonitor::factory()->create(['worker_type' => WorkerType::QUEUE_WORK]);
    JobMonitor::factory()->create(['worker_type' => WorkerType::HORIZON]);

    expect(QueryBuilderHelper::queueWorkOnly()->count())->toBe(1);
});

test('between returns jobs within date range', function () {
    JobMonitor::factory()->create(['queued_at' => Carbon::parse('2025-06-15')]);
    JobMonitor::factory()->create(['queued_at' => Carbon::parse('2025-07-15')]);
    JobMonitor::factory()->create(['queued_at' => Carbon::parse('2025-08-15')]);

    expect(QueryBuilderHelper::between(
        Carbon::parse('2025-06-01'),
        Carbon::parse('2025-07-31'),
    )->count())->toBe(2);
});

test('recentlyCompleted returns completed jobs in order', function () {
    JobMonitor::factory()->create(['status' => JobStatus::COMPLETED, 'completed_at' => now()]);
    JobMonitor::factory()->create(['status' => JobStatus::COMPLETED, 'completed_at' => now()->subHour()]);
    JobMonitor::factory()->failed()->create();

    expect(QueryBuilderHelper::recentlyCompleted(10)->count())->toBe(2);
});

test('withTag returns jobs containing a specific tag', function () {
    JobMonitor::factory()->create(['tags' => ['email', 'urgent']]);
    JobMonitor::factory()->create(['tags' => ['sms']]);

    expect(QueryBuilderHelper::withTag('email')->count())->toBe(1);
});

test('withAllTags returns jobs matching all tags', function () {
    JobMonitor::factory()->create(['tags' => ['email', 'urgent']]);
    JobMonitor::factory()->create(['tags' => ['email']]);

    expect(QueryBuilderHelper::withAllTags(['email', 'urgent'])->count())->toBe(1);
});

test('withAnyTag returns jobs matching any tag', function () {
    JobMonitor::factory()->create(['tags' => ['email']]);
    JobMonitor::factory()->create(['tags' => ['sms']]);
    JobMonitor::factory()->create(['tags' => ['push']]);

    expect(QueryBuilderHelper::withAnyTag(['email', 'sms'])->count())->toBe(2);
});

test('longRunning returns jobs processing for too long', function () {
    JobMonitor::factory()->processing()->create(['started_at' => now()->subMinutes(20)]);
    JobMonitor::factory()->processing()->create(['started_at' => now()->subMinutes(5)]);

    expect(QueryBuilderHelper::longRunning(10)->count())->toBe(1);
});

test('stuck is alias for longRunning', function () {
    JobMonitor::factory()->processing()->create(['started_at' => now()->subMinutes(45)]);

    expect(QueryBuilderHelper::stuck(30)->count())->toBe(1);
});
