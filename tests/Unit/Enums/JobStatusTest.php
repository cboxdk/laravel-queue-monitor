<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\Enums\JobStatus;

test('job status has all expected cases', function () {
    $cases = JobStatus::cases();

    expect($cases)->toHaveCount(6);
    expect(JobStatus::values())->toEqual([
        'queued',
        'processing',
        'completed',
        'failed',
        'timeout',
        'cancelled',
    ]);
});

test('isFinished returns true for finished statuses', function (JobStatus $status) {
    expect($status->isFinished())->toBeTrue();
})->with([
    JobStatus::COMPLETED,
    JobStatus::FAILED,
    JobStatus::TIMEOUT,
    JobStatus::CANCELLED,
]);

test('isFinished returns false for active statuses', function (JobStatus $status) {
    expect($status->isFinished())->toBeFalse();
})->with([
    JobStatus::QUEUED,
    JobStatus::PROCESSING,
]);

test('isSuccessful returns true only for completed', function () {
    expect(JobStatus::COMPLETED->isSuccessful())->toBeTrue();
    expect(JobStatus::FAILED->isSuccessful())->toBeFalse();
});

test('isFailed returns true for failed and timeout', function (JobStatus $status) {
    expect($status->isFailed())->toBeTrue();
})->with([
    JobStatus::FAILED,
    JobStatus::TIMEOUT,
]);

test('label returns correct display name', function () {
    expect(JobStatus::COMPLETED->label())->toBe('Completed');
    expect(JobStatus::FAILED->label())->toBe('Failed');
    expect(JobStatus::PROCESSING->label())->toBe('Processing');
});

test('color returns appropriate UI color', function () {
    expect(JobStatus::COMPLETED->color())->toBe('green');
    expect(JobStatus::FAILED->color())->toBe('red');
    expect(JobStatus::PROCESSING->color())->toBe('blue');
});
