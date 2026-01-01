<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \PHPeek\LaravelQueueMonitor\LaravelQueueMonitor
 */
class LaravelQueueMonitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \PHPeek\LaravelQueueMonitor\LaravelQueueMonitor::class;
    }
}
