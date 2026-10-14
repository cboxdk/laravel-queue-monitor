<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Support\InFlightAttempts;

test('it remembers the attempt started for a job key', function () {
    $attempts = new InFlightAttempts;
    $attempts->remember('job-1', 1);
    $attempts->remember('job-2', 3);

    expect($attempts->attemptFor('job-1'))->toBe(1)
        ->and($attempts->attemptFor('job-2'))->toBe(3)
        ->and($attempts->attemptFor('unknown'))->toBeNull();
});

test('a later start of the same job replaces its attempt', function () {
    $attempts = new InFlightAttempts;
    $attempts->remember('job-1', 1);
    $attempts->remember('job-1', 2);

    expect($attempts->attemptFor('job-1'))->toBe(2);
});

test('it keeps at most MAX_TRACKED jobs, dropping the one started longest ago', function () {
    $attempts = new InFlightAttempts;

    for ($i = 0; $i <= InFlightAttempts::MAX_TRACKED; $i++) {
        $attempts->remember("job-{$i}", 1);
    }

    expect($attempts->attemptFor('job-0'))->toBeNull()
        ->and($attempts->attemptFor('job-1'))->toBe(1)
        ->and($attempts->attemptFor('job-'.InFlightAttempts::MAX_TRACKED))->toBe(1);
});

test('restarting a job refreshes its place so it is not the next evicted', function () {
    $attempts = new InFlightAttempts;

    for ($i = 0; $i < InFlightAttempts::MAX_TRACKED; $i++) {
        $attempts->remember("job-{$i}", 1);
    }

    $attempts->remember('job-0', 2);
    $attempts->remember('job-new', 1);

    expect($attempts->attemptFor('job-0'))->toBe(2)
        ->and($attempts->attemptFor('job-1'))->toBeNull();
});

test('it is shared across the process as a singleton', function () {
    app(InFlightAttempts::class)->remember('job-1', 4);

    expect(app(InFlightAttempts::class)->attemptFor('job-1'))->toBe(4);
});
