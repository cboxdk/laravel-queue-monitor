<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\DataTransferObjects\WorkerContextData;
use PHPeek\LaravelQueueMonitor\Enums\WorkerType;

test('creates worker context data from constructor', function () {
    $data = new WorkerContextData(
        serverName: 'web-1',
        workerId: 'worker-12345',
        workerType: WorkerType::QUEUE_WORK
    );

    expect($data->serverName)->toBe('web-1');
    expect($data->workerId)->toBe('worker-12345');
    expect($data->workerType)->toBe(WorkerType::QUEUE_WORK);
});

test('creates from array', function () {
    $data = WorkerContextData::fromArray([
        'server_name' => 'web-2',
        'worker_id' => 'horizon-supervisor-1',
        'worker_type' => 'horizon',
    ]);

    expect($data->serverName)->toBe('web-2');
    expect($data->workerId)->toBe('horizon-supervisor-1');
    expect($data->workerType)->toBe(WorkerType::HORIZON);
});

test('converts to array', function () {
    $data = new WorkerContextData(
        serverName: 'web-1',
        workerId: 'worker-123',
        workerType: WorkerType::QUEUE_WORK
    );

    $array = $data->toArray();

    expect($array)->toBe([
        'server_name' => 'web-1',
        'worker_id' => 'worker-123',
        'worker_type' => 'queue_work',
    ]);
});

test('isHorizon returns correct value', function () {
    $queueWork = new WorkerContextData('web-1', 'worker-123', WorkerType::QUEUE_WORK);
    $horizon = new WorkerContextData('web-1', 'supervisor-1', WorkerType::HORIZON);

    expect($queueWork->isHorizon())->toBeFalse();
    expect($horizon->isHorizon())->toBeTrue();
});

test('generates unique identifier', function () {
    $data = new WorkerContextData('web-1', 'worker-123', WorkerType::QUEUE_WORK);

    expect($data->uniqueIdentifier())->toBe('web-1:worker-123');
});
