<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPeek\LaravelQueueMonitor\Tests\TestCase;

uses(TestCase::class)
    ->beforeEach(fn () => $this->artisan('migrate:fresh'))
    ->in(__DIR__);

uses(RefreshDatabase::class)->in('Feature');

// Expectations
expect()->extend('toBeJobStatus', function (string $expected) {
    return $this->value === $expected || $this->value->value === $expected;
});

expect()->extend('toHaveMetrics', function () {
    return $this->value->cpuTimeMs !== null ||
           $this->value->memoryPeakMb !== null ||
           $this->value->durationMs !== null;
});
