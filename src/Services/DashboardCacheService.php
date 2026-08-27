<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Services;

use Cbox\LaravelQueueMonitor\Services\Contracts\DashboardCacheServiceContract;
use Composer\InstalledVersions;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class DashboardCacheService implements DashboardCacheServiceContract
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

        // A value serialized by a previous release can come back as an
        // __PHP_Incomplete_Class (or hold one) when its class no longer matches;
        // treat that as a miss and recompute rather than returning a broken value.
        if ($cached !== null && ! self::isStaleCache($cached)) {
            return $cached;
        }

        $value = $callback();
        $cache->put($fullKey, $value, $ttl ?? $cacheTtl);

        return $value;
    }

    /**
     * Whether a cached value is (or shallowly contains) an incomplete class —
     * the signature of a value serialized by an incompatible earlier release.
     */
    public static function isStaleCache(mixed $value): bool
    {
        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof \__PHP_Incomplete_Class) {
                    return true;
                }
            }
        }

        return false;
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

        // Namespace the cache by the installed package release. Cached values
        // now include typed value objects, which serialize as objects; a
        // persistent cache (redis/database) outlives a deploy, and a value
        // object serialized by a previous release unserializes to
        // __PHP_Incomplete_Class and fails the new strict return types. Keying
        // by the release orphans every pre-deploy entry so nothing stale is
        // ever read back.
        return $prefix.'r'.self::releaseFingerprint().':';
    }

    private static function releaseFingerprint(): string
    {
        if (class_exists(InstalledVersions::class)) {
            $reference = InstalledVersions::getReference('cboxdk/laravel-queue-monitor')
                ?? InstalledVersions::getVersion('cboxdk/laravel-queue-monitor');

            if (is_string($reference) && $reference !== '') {
                return substr(hash('xxh128', $reference), 0, 12);
            }
        }

        return 'dev';
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
