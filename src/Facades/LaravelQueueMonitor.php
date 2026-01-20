<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Cbox\LaravelQueueMonitor\LaravelQueueMonitor
 */
class LaravelQueueMonitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Cbox\LaravelQueueMonitor\LaravelQueueMonitor::class;
    }
}
