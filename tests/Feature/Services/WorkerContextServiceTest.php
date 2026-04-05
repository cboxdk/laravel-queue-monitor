<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\WorkerContextData;
use Cbox\LaravelQueueMonitor\Enums\WorkerType;
use Cbox\LaravelQueueMonitor\Services\WorkerContextService;

test('capture returns WorkerContextData', function () {
    $service = app(WorkerContextService::class);
    $context = $service->capture();

    expect($context)->toBeInstanceOf(WorkerContextData::class);
    expect($context->serverName)->toBeString()->not->toBeEmpty();
    expect($context->workerId)->toBeString()->not->toBeEmpty();
    expect($context->workerType)->toBeInstanceOf(WorkerType::class);
});

test('defaults to queue_work worker type', function () {
    $service = app(WorkerContextService::class);
    $context = $service->capture();

    expect($context->workerType)->toBe(WorkerType::QUEUE_WORK);
});

test('server name falls back to gethostname', function () {
    config()->set('queue-monitor.worker_detection.server_name_callable', null);

    $service = new WorkerContextService;
    $context = $service->capture();

    expect($context->serverName)->toBe(gethostname() ?: 'unknown');
});

test('uses custom server name callable when configured', function () {
    config()->set('queue-monitor.worker_detection.server_name_callable', fn () => 'custom-server');

    $service = new WorkerContextService;
    $context = $service->capture();

    expect($context->serverName)->toBe('custom-server');
});

test('worker id includes pid for queue work', function () {
    $service = app(WorkerContextService::class);
    $context = $service->capture();

    expect($context->workerId)->toStartWith('worker-');
});

test('getUniqueIdentifier returns string', function () {
    $service = app(WorkerContextService::class);
    $identifier = $service->getUniqueIdentifier();

    expect($identifier)->toBeString()->not->toBeEmpty();
});

test('horizon detection respects config', function () {
    config()->set('queue-monitor.worker_detection.horizon_detection', false);

    $service = new WorkerContextService;
    $context = $service->capture();

    // With horizon detection disabled and no autoscale env, should default to queue_work
    expect($context->workerType)->toBe(WorkerType::QUEUE_WORK);
});
