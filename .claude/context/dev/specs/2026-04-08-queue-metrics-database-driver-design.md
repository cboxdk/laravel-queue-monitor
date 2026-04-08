# Database Driver for laravel-queue-metrics

**Date:** 2026-04-08
**Status:** Approved
**Package:** cboxdk/laravel-queue-metrics

## Problem

laravel-queue-metrics has a hard Redis dependency. All 5 repository implementations are Redis-only. The package's config advertises `storage.driver` but only `redis` works. laravel-queue-monitor markets itself as driver-agnostic, but its metrics dependency breaks that promise for users running database queues without Redis.

## Target Audience

Laravel applications using database queue driver with low-scale workloads (< 10 workers). These teams typically run on Forge/Ploi with managed databases and no Redis infrastructure. They want observability without adding infrastructure dependencies.

## Design

### Architecture

One new `DatabaseMetricsStore` class implementing the same public API as `RedisMetricsStore`, operating against 4 existing generic tables from migration `2024_01_01_000001_create_queue_metrics_storage_tables`:

- `queue_metrics_keys` — key-value storage (strings)
- `queue_metrics_hashes` — hash storage (JSON `data` column)
- `queue_metrics_sets` — set storage (key + member)
- `queue_metrics_sorted_sets` — sorted set storage (key + member + score)

5 new `Database*Repository` classes that mirror the existing `Redis*Repository` classes but inject `DatabaseMetricsStore` instead of `RedisMetricsStore`.

4 internal Eloquent models for database access: `MetricsKey`, `MetricsHash`, `MetricsSet`, `MetricsSortedSet`. These are not exposed in the public API.

### Driver Selection

The service provider reads `config('queue-metrics.storage.driver')` and resolves repositories accordingly. The existing `repositories` config mapping supports explicit overrides:

```php
'repositories' => [
    JobMetricsRepository::class => null,      // null = resolved from driver
    QueueMetricsRepository::class => null,
    WorkerRepository::class => null,
    BaselineRepository::class => null,
    WorkerHeartbeatRepository::class => null,
],
```

When a repository value is `null`, the service provider uses the driver setting to determine the implementation. When set explicitly, it uses that class regardless of driver. This preserves the existing extensibility — users can mix drivers or provide custom implementations.

### Operation Mapping

| Redis operation | Database equivalent |
|---|---|
| `setHash($key, $data)` | `UPSERT` into `_hashes` with JSON `data` column |
| `getHash($key)` | `SELECT data FROM _hashes WHERE key = ?` |
| `incrementHashField($key, $field, $value)` | `DB::transaction` → SELECT FOR UPDATE → decode JSON → increment → update |
| `addToSortedSet($key, $members)` | `UPSERT` into `_sorted_sets` per member |
| `getSortedSetByScore($key, $min, $max)` | `SELECT FROM _sorted_sets WHERE key = ? AND score BETWEEN ? AND ? ORDER BY score` |
| `removeSortedSetByRank($key, $start, $stop)` | Subquery with `LIMIT/OFFSET` delete |
| `addToSet($key, $members)` | `INSERT IGNORE` into `_sets` |
| `getSetMembers($key)` | `SELECT member FROM _sets WHERE key = ?` |
| `set($key, $value, $ttl)` | `UPSERT` into `_keys` with `expires_at` |
| `get($key)` | `SELECT FROM _keys WHERE key = ? AND (expires_at IS NULL OR expires_at > NOW())` |
| `pipeline($callback)` | `DB::transaction($callback)` |
| `transaction($callback)` | `DB::transaction($callback)` |
| `scanKeys($pattern)` | `SELECT key FROM _keys WHERE key LIKE ?` (glob converted to SQL LIKE) |

### Lua Script Replacement

Redis Lua scripts (primarily `UpdateWorkerHeartbeat.lua`) are replaced with database transactions using `SELECT ... FOR UPDATE`:

```php
DB::transaction(function () use ($key, $indexKey, $workerId, ...) {
    $existing = MetricsHash::where('key', $key)->lockForUpdate()->first();
    // Decode JSON data, apply state transition logic, update counters
    MetricsHash::updateOrCreate(['key' => $key], ['data' => $updated, 'expires_at' => ...]);
    MetricsSortedSet::updateOrCreate(
        ['key' => $indexKey, 'member' => $workerId],
        ['score' => now()->timestamp]
    );
});
```

### Cleanup and TTL

One new scheduled command: `queue-metrics:cleanup-database`. Runs every minute, registered in the service provider alongside existing scheduled tasks. Only active when driver is `database`.

```php
// Delete expired rows from all tables with expires_at
MetricsKey::where('expires_at', '<', now())->delete();
MetricsHash::where('expires_at', '<', now())->delete();
MetricsSortedSet::where('expires_at', '<', now())->delete();

// Trim sorted sets exceeding max_samples_per_key
MetricsSortedSet::select('key')
    ->groupBy('key')
    ->havingRaw('COUNT(*) > ?', [$maxSamples])
    ->each(function ($row) use ($maxSamples) {
        // Keep newest $maxSamples per key, delete the rest
    });
```

Sets table has no `expires_at` — cleanup happens via `removeFromSet()` calls from repositories (same as Redis `SREM`).

### Config Changes

```php
'storage' => [
    'driver' => env('QUEUE_METRICS_STORAGE', 'redis'),
    'connection' => env('QUEUE_METRICS_CONNECTION', 'default'),
    'prefix' => 'queue_metrics',
    // Maximum retained samples per metric key.
    // Recommended: 1000 for Redis, 500 for database driver.
    'max_samples_per_key' => env('QUEUE_METRICS_MAX_SAMPLES', 1000),
    'cleanup_chunk_size' => 1000,
    'ttl' => [
        'raw' => 3600,
        'aggregated' => 604800,
        'baseline' => 2592000,
    ],
],

'repositories' => [
    JobMetricsRepository::class => null,
    QueueMetricsRepository::class => null,
    WorkerRepository::class => null,
    BaselineRepository::class => null,
    WorkerHeartbeatRepository::class => null,
],
```

## New Files

| File | Purpose |
|---|---|
| `src/Support/DatabaseMetricsStore.php` | Eloquent-based equivalent of `RedisMetricsStore` |
| `src/Models/MetricsKey.php` | Eloquent model for `queue_metrics_keys` |
| `src/Models/MetricsHash.php` | Eloquent model for `queue_metrics_hashes` |
| `src/Models/MetricsSet.php` | Eloquent model for `queue_metrics_sets` |
| `src/Models/MetricsSortedSet.php` | Eloquent model for `queue_metrics_sorted_sets` |
| `src/Repositories/DatabaseJobMetricsRepository.php` | Database implementation of `JobMetricsRepository` |
| `src/Repositories/DatabaseQueueMetricsRepository.php` | Database implementation of `QueueMetricsRepository` |
| `src/Repositories/DatabaseWorkerRepository.php` | Database implementation of `WorkerRepository` |
| `src/Repositories/DatabaseBaselineRepository.php` | Database implementation of `BaselineRepository` |
| `src/Repositories/DatabaseWorkerHeartbeatRepository.php` | Database implementation of `WorkerHeartbeatRepository` |
| `src/Console/CleanupDatabaseCommand.php` | Scheduled cleanup for expired rows and trimming |
| `tests/Feature/Repositories/Database*Test.php` | Standalone tests per database repository (5 files) |

## Modified Files

| File | Change |
|---|---|
| `src/LaravelQueueMetricsServiceProvider.php` | Resolve repositories from driver when config is `null` |
| `config/queue-metrics.php` | Add `max_samples_per_key`, `cleanup_chunk_size`, change repositories defaults to `null` |

## Documentation

README and config must clearly state:

> **Database driver is for low-scale workloads** (< 10 workers). At higher scale, metrics writes create contention on the same database your queue jobs use. Use the Redis driver for production with moderate to high workloads.

> Recommended `max_samples_per_key`: 1000 for Redis, 500 for database.

## Not In Scope

- Cluster-awareness / host registration (separate spec)
- Ceiling events and alerting (separate spec)
- Multi-host coordination (separate spec)
- Shared abstract tests between Redis and database drivers
- Domain-specific tables (we use the existing generic KV-store migration)
