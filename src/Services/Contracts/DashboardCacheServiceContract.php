<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services\Contracts;

interface DashboardCacheServiceContract
{
    public function scopedKey(string $key): string;

    public function bust(): void;

    public function remember(string $key, \Closure $callback, ?int $ttl = null): mixed;
}
