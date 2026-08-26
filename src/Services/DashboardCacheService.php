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

        // Throttle the version bump: a busy queue fires this on every job
        // lifecycle write, and without a throttle the version increments
        // faster than any cache entry can be reused — so the statistics cache
        // is perpetually cold exactly when the table is largest. The leading
        // edge still invalidates immediately, so new data shows up promptly;
        // subsequent writes within the window are absorbed into that one bump.
        $throttleSeconds = $this->bustThrottleSeconds();
        if ($throttleSeconds > 0 && ! $cache->add($this->throttleKey(), 1, now()->addSeconds($throttleSeconds))) {
            return;
        }

        $versionKey = $this->versionKey();
        $cache->add($versionKey, 1, now()->addYear());
        $cache->increment($versionKey);
    }

    private function bustThrottleSeconds(): int
    {
        $value = config('queue-monitor.cache.bust_throttle_seconds', 5);

        return is_numeric($value) ? max(0, (int) $value) : 5;
    }

    private function throttleKey(): string
    {
        return $this->cachePrefix().'bust_throttle';
    }

    public function remember(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        if (! config('queue-monitor.cache.enabled', true)) {
            return $callback();
        }

        /** @var int $cacheTtl */
        $cacheTtl = config('queue-monitor.cache.ttl', 300);

        $cache = $this->cacheStore();
        $fullKey = $this->scopedKey($key);
        $cached = $cache->get($fullKey);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $cache->put($fullKey, $value, $ttl ?? $cacheTtl);

        return $value;
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
