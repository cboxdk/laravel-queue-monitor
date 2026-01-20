<?php

declare(strict_types=1);

use Carbon\Carbon;
use Cbox\LaravelQueueMonitor\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function yesterday(): Carbon
{
    return Carbon::yesterday();
}

// Expectations
expect()->extend('toBeJobStatus', function (string $expected) {
    return $this->value === $expected || $this->value->value === $expected;
});

expect()->extend('toHaveMetrics', function () {
    return $this->value->cpuTimeMs !== null ||
           $this->value->memoryPeakMb !== null ||
           $this->value->durationMs !== null;
});
