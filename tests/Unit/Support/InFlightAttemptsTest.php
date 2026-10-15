<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
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

test('it remembers when the attempt started, to the microsecond', function () {
    $attempts = new InFlightAttempts;
    $startedAt = CarbonImmutable::parse('2026-10-15 12:00:00.950123');

    $attempts->remember('job-1', 2, $startedAt);

    expect($attempts->startedAtFor('job-1', 2)?->format('Y-m-d H:i:s.u'))->toBe('2026-10-15 12:00:00.950123')
        ->and($attempts->startedAtFor('job-1', 1))->toBeNull()
        ->and($attempts->startedAtFor('unknown', 1))->toBeNull();
});

test('it records the start as now when none is given', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-15 12:00:00.250'));
    $attempts = new InFlightAttempts;

    $attempts->remember('job-1', 1);

    expect($attempts->startedAtFor('job-1', 1)?->format('H:i:s.v'))->toBe('12:00:00.250');
});

test('forgetting a start keeps the attempt, and ignores another attempt', function () {
    $attempts = new InFlightAttempts;
    $attempts->remember('job-1', 2);

    $attempts->forgetStart('job-1', 1);
    expect($attempts->startedAtFor('job-1', 2))->not->toBeNull();

    $attempts->forgetStart('job-1', 2);
    $attempts->forgetStart('unknown', 1);

    expect($attempts->startedAtFor('job-1', 2))->toBeNull()
        ->and($attempts->attemptFor('job-1'))->toBe(2);
});

test('start times are bounded with the attempts', function () {
    $attempts = new InFlightAttempts;

    for ($i = 0; $i <= InFlightAttempts::MAX_TRACKED; $i++) {
        $attempts->remember("job-{$i}", 1);
    }

    expect($attempts->startedAtFor('job-0', 1))->toBeNull()
        ->and($attempts->startedAtFor('job-'.InFlightAttempts::MAX_TRACKED, 1))->not->toBeNull();
});
