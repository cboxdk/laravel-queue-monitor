<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Enums\WorkerType;
use PHPeek\LaravelQueueMonitor\Models\JobMonitor;
use PHPeek\LaravelQueueMonitor\Utilities\QueryBuilderHelper;

test('lastHours returns jobs from specified time range', function () {
    JobMonitor::factory()->create(['queued_at' => now()->subHours(25)]);
    JobMonitor::factory()->count(3)->create(['queued_at' => now()->subHours(12)]);

    $recent = QueryBuilderHelper::lastHours(24)->get();

    expect($recent)->toHaveCount(3);
});

test('today returns only jobs from current day', function () {
    JobMonitor::factory()->create(['queued_at' => yesterday()]);
    JobMonitor::factory()->count(5)->create(['queued_at' => today()->addHours(12)]);

    $todayJobs = QueryBuilderHelper::today()->get();

    expect($todayJobs)->toHaveCount(5);
});

test('slow returns jobs exceeding threshold', function () {
    JobMonitor::factory()->create(['duration_ms' => 1000]);
    JobMonitor::factory()->count(2)->create(['duration_ms' => 6000]);

    $slow = QueryBuilderHelper::slow(5000)->get();

    expect($slow)->toHaveCount(2);
    expect($slow->every(fn ($job) => $job->duration_ms > 5000))->toBeTrue();
});

test('failedWith returns jobs with specific exception', function () {
    JobMonitor::factory()->failed()->create(['exception_class' => 'RuntimeException']);
    JobMonitor::factory()->failed()->create(['exception_class' => 'RuntimeException']);
    JobMonitor::factory()->failed()->create(['exception_class' => 'LogicException']);

    $runtime = QueryBuilderHelper::failedWith('RuntimeException')->get();

    expect($runtime)->toHaveCount(2);
});

test('horizonOnly returns only horizon worker jobs', function () {
    JobMonitor::factory()->count(3)->create(['worker_type' => WorkerType::QUEUE_WORK]);
    JobMonitor::factory()->count(2)->horizon()->create();

    $horizonJobs = QueryBuilderHelper::horizonOnly()->get();

    expect($horizonJobs)->toHaveCount(2);
    expect($horizonJobs->every(fn ($job) => $job->worker_type === WorkerType::HORIZON))->toBeTrue();
});

test('withTag returns jobs with specific tag', function () {
    JobMonitor::factory()->withTags(['email', 'priority'])->create();
    JobMonitor::factory()->withTags(['sms'])->create();

    $emailJobs = QueryBuilderHelper::withTag('email')->get();

    expect($emailJobs)->toHaveCount(1);
});

test('longRunning returns processing jobs exceeding time limit', function () {
    JobMonitor::factory()->processing()->create([
        'started_at' => now()->subMinutes(5),
    ]);

    JobMonitor::factory()->processing()->create([
        'started_at' => now()->subMinutes(15),
    ]);

    $longRunning = QueryBuilderHelper::longRunning(10)->get();

    expect($longRunning)->toHaveCount(1);
});
