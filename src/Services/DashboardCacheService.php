<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class DashboardCacheService
{
    public function scopedKey(string $key): string
    {
        return $this->cachePrefix().'v'.$this->version().':'.$key;
    }

    public function bust(): void
    {
        if (! config('queue-monitor.cache.enabled', true)) {
            return;
        }

        $cache = $this->cacheStore();
        $versionKey = $this->versionKey();

        $cache->add($versionKey, 1, now()->addYear());
        $cache->increment($versionKey);
    }

    private function version(): int
    {
        if (! config('queue-monitor.cache.enabled', true)) {
            return 1;
        }

        return (int) $this->cacheStore()->rememberForever(
            $this->versionKey(),
            fn (): int => 1,
        );
    }

    private function versionKey(): string
    {
        return $this->cachePrefix().'dashboard_cache_version';
    }

    private function cachePrefix(): string
    {
        /** @var string $prefix */
        $prefix = config('queue-monitor.cache.prefix', 'queue_monitor_');

        return $prefix;
    }

    private function cacheStore(): Repository
    {
        /** @var string|null $cacheStore */
        $cacheStore = config('queue-monitor.cache.store');

        return $cacheStore !== null
            ? Cache::store($cacheStore)
            : Cache::store();
    }
}
