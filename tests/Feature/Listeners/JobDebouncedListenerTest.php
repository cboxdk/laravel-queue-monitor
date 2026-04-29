<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Listeners\JobDebouncedListener;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

test('debounced status is a terminal state', function () {
    expect(JobStatus::DEBOUNCED->isFinished())->toBeTrue();
    expect(JobStatus::DEBOUNCED->isSuccessful())->toBeFalse();
    expect(JobStatus::DEBOUNCED->isFailed())->toBeFalse();
    expect(JobStatus::DEBOUNCED->label())->toBe('Debounced');
    expect(JobStatus::DEBOUNCED->color())->toBe('slate');
});

test('debounced listener marks queued job as debounced', function () {
    $job = JobMonitor::factory()->queued()->create(['job_id' => 'test-job-123']);

    $event = new stdClass;
    $event->id = 'test-job-123';

    $listener = new JobDebouncedListener;
    $listener->handle($event);

    $job->refresh();
    expect($job->status)->toBe(JobStatus::DEBOUNCED);
    expect($job->completed_at)->not->toBeNull();
});

test('debounced listener does nothing for unknown job_id', function () {
    $event = new stdClass;
    $event->id = 'nonexistent-job';

    $listener = new JobDebouncedListener;
    $listener->handle($event);

    expect(JobMonitor::count())->toBe(0);
});

test('debounced listener never throws', function () {
    $event = new stdClass;
    // No id property

    $listener = new JobDebouncedListener;
    $listener->handle($event);

    expect(JobMonitor::count())->toBe(0);
});
