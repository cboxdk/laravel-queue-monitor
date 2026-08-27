<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\GlobalStatistics;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\StatisticsRepositoryContract;
use Cbox\LaravelQueueMonitor\Services\DashboardCacheService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config()->set('queue-monitor.cache.enabled', true);
});

it('detects an incomplete class, top-level and nested', function () {
    $incomplete = unserialize('O:24:"Some\\Removed\\CachedClass":0:{}');

    expect(DashboardCacheService::isStaleCache($incomplete))->toBeTrue();
    expect(DashboardCacheService::isStaleCache([$incomplete]))->toBeTrue();
    expect(DashboardCacheService::isStaleCache(['total' => 1]))->toBeFalse();
    expect(DashboardCacheService::isStaleCache(new GlobalStatistics(total: 1)))->toBeFalse();
});

it('recomputes global statistics when the cache holds a value from an incompatible release', function () {
    JobMonitor::factory()->count(3)->create();

    $repo = app(StatisticsRepositoryContract::class);
    $cache = app(DashboardCacheService::class);

    // A value serialized by a class that no longer exists, exactly as a
    // pre-deploy cached value object comes back after a deploy.
    $stale = unserialize('O:24:"Some\\Removed\\CachedClass":0:{}');
    Cache::put($cache->scopedKey('global_statistics'), $stale, 300);

    // Must not return the incomplete class into the strict return type.
    $result = $repo->getGlobalStatistics();

    expect($result)->toBeInstanceOf(GlobalStatistics::class);
    expect($result->total)->toBe(3);
});

it('namespaces the cache key by release so a deploy orphans stale entries', function () {
    $key = app(DashboardCacheService::class)->scopedKey('global_statistics');

    // r<fingerprint> segment isolates one release's cache from another's.
    expect($key)->toMatch('/queue_monitor_r[0-9a-f]+:v\d+:global_statistics/');
});
