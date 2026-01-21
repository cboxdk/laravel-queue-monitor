<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\WorkerType;

test('worker type has all expected cases', function () {
    expect(WorkerType::cases())->toHaveCount(3);
    expect(WorkerType::values())->toEqual(['queue_work', 'horizon', 'autoscale']);
});

test('isAutoscale returns true only for autoscale', function () {
    expect(WorkerType::AUTOSCALE->isAutoscale())->toBeTrue();
    expect(WorkerType::QUEUE_WORK->isAutoscale())->toBeFalse();
});

test('isHorizon returns true only for horizon', function () {
    expect(WorkerType::HORIZON->isHorizon())->toBeTrue();
    expect(WorkerType::QUEUE_WORK->isHorizon())->toBeFalse();
});

test('isQueueWork returns true only for queue_work', function () {
    expect(WorkerType::QUEUE_WORK->isQueueWork())->toBeTrue();
    expect(WorkerType::HORIZON->isQueueWork())->toBeFalse();
});

test('label returns correct display name', function () {
    expect(WorkerType::QUEUE_WORK->label())->toBe('Queue Worker');
    expect(WorkerType::HORIZON->label())->toBe('Horizon');
    expect(WorkerType::AUTOSCALE->label())->toBe('Autoscale');
});

test('icon returns appropriate identifier', function () {
    expect(WorkerType::QUEUE_WORK->icon())->toBe('terminal');
    expect(WorkerType::HORIZON->icon())->toBe('dashboard');
    expect(WorkerType::AUTOSCALE->icon())->toBe('chip');
});
