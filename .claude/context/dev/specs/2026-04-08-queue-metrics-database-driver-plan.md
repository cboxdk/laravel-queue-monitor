# Queue Metrics Database Driver — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a database storage driver to laravel-queue-metrics so users without Redis can use the full metrics stack.

**Architecture:** A `DatabaseMetricsStore` wrapping 4 Eloquent models that map to the existing generic KV-store migration tables. 5 `Database*Repository` classes implement the existing contracts using this store. Driver selection via config determines which implementations the service provider binds.

**Tech Stack:** Laravel Eloquent, Pest, Orchestra Testbench, SQLite in-memory for tests.

**Package root:** This work is done in the `cboxdk/laravel-queue-metrics` package (clone from GitHub, not in vendor/).

**Reference files:** Each task references the corresponding Redis implementation. Read it before implementing — the database version must produce identical behavior for the same inputs.

---

### Task 1: Eloquent Models

**Files:**
- Create: `src/Models/MetricsKey.php`
- Create: `src/Models/MetricsHash.php`
- Create: `src/Models/MetricsSet.php`
- Create: `src/Models/MetricsSortedSet.php`

- [ ] **Step 1: Create MetricsKey model**

```php
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMetrics\Models;

use Illuminate\Database\Eloquent\Model;

class MetricsKey extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'expires_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        $prefix = config('queue-metrics.storage.prefix', 'queue_metrics');

        return $prefix . '_keys';
    }

    public function getConnectionName(): ?string
    {
        $connection = config('queue-metrics.storage.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
```

- [ ] **Step 2: Create MetricsHash model**

```php
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMetrics\Models;

use Illuminate\Database\Eloquent\Model;

class MetricsHash extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'data', 'expires_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'expires_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        $prefix = config('queue-metrics.storage.prefix', 'queue_metrics');

        return $prefix . '_hashes';
    }

    public function getConnectionName(): ?string
    {
        $connection = config('queue-metrics.storage.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
```

- [ ] **Step 3: Create MetricsSet model**

```php
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMetrics\Models;

use Illuminate\Database\Eloquent\Model;

class MetricsSet extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['key', 'member', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        $prefix = config('queue-metrics.storage.prefix', 'queue_metrics');

        return $prefix . '_sets';
    }

    public function getConnectionName(): ?string
    {
        $connection = config('queue-metrics.storage.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }
}
```

- [ ] **Step 4: Create MetricsSortedSet model**

```php
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMetrics\Models;

use Illuminate\Database\Eloquent\Model;

class MetricsSortedSet extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['key', 'member', 'score', 'expires_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:4',
            'expires_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        $prefix = config('queue-metrics.storage.prefix', 'queue_metrics');

        return $prefix . '_sorted_sets';
    }

    public function getConnectionName(): ?string
    {
        $connection = config('queue-metrics.storage.connection');

        return is_string($connection) ? $connection : parent::getConnectionName();
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Models/
git commit -m "feat: add Eloquent models for database metrics storage"
```

---

### Task 2: DatabaseMetricsStore

**Files:**
- Create: `src/Support/DatabaseMetricsStore.php`
- Create: `tests/Feature/Support/DatabaseMetricsStoreTest.php`
- Reference: `src/Support/RedisMetricsStore.php`

- [ ] **Step 1: Write tests for core operations**

```php
<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Support\DatabaseMetricsStore;

beforeEach(function () {
    $this->store = new DatabaseMetricsStore();
});

// --- String operations ---

test('set and get a string value', function () {
    $this->store->set('test:key', 'hello');
    expect($this->store->get('test:key'))->toBe('hello');
});

test('set with TTL expires key', function () {
    $this->store->set('test:ttl', 'value', 1);
    expect($this->store->get('test:ttl'))->toBe('value');

    $this->travel(2)->seconds();
    expect($this->store->get('test:ttl'))->toBeNull();
});

test('exists returns true for existing key', function () {
    $this->store->set('test:exists', 'yes');
    expect($this->store->exists('test:exists'))->toBeTrue();
    expect($this->store->exists('test:nope'))->toBeFalse();
});

test('delete removes key', function () {
    $this->store->set('test:del', 'value');
    $this->store->delete('test:del');
    expect($this->store->get('test:del'))->toBeNull();
});

// --- Hash operations ---

test('setHash and getHash', function () {
    $this->store->setHash('test:hash', ['name' => 'Alice', 'age' => '30']);
    expect($this->store->getHash('test:hash'))->toBe(['name' => 'Alice', 'age' => '30']);
});

test('getHashField returns single field', function () {
    $this->store->setHash('test:hash', ['a' => '1', 'b' => '2']);
    expect($this->store->getHashField('test:hash', 'a'))->toBe('1');
});

test('incrementHashField increments integer', function () {
    $this->store->setHash('test:inc', ['count' => '5']);
    $this->store->incrementHashField('test:inc', 'count', 3);
    expect($this->store->getHashField('test:inc', 'count'))->toBe(8);
});

test('incrementHashField increments float', function () {
    $this->store->setHash('test:incf', ['total' => '1.5']);
    $this->store->incrementHashField('test:incf', 'total', 2.5);
    expect($this->store->getHashField('test:incf', 'total'))->toBe(4.0);
});

test('incrementHashField creates field if missing', function () {
    $this->store->setHash('test:inc-new', ['other' => 'x']);
    $this->store->incrementHashField('test:inc-new', 'count', 1);
    expect($this->store->getHashField('test:inc-new', 'count'))->toBe(1);
});

// --- Sorted set operations ---

test('addToSortedSet and getSortedSetByScore', function () {
    $this->store->addToSortedSet('test:zset', ['alice' => 10, 'bob' => 20, 'carol' => 15]);
    $result = $this->store->getSortedSetByScore('test:zset', '0', '100');
    expect($result)->toBe(['alice', 'carol', 'bob']);
});

test('getSortedSetByRank returns by position', function () {
    $this->store->addToSortedSet('test:zset', ['a' => 1, 'b' => 2, 'c' => 3]);
    expect($this->store->getSortedSetByRank('test:zset', 0, 1))->toBe(['a', 'b']);
});

test('countSortedSetByScore counts within range', function () {
    $this->store->addToSortedSet('test:zset', ['a' => 10, 'b' => 20, 'c' => 30]);
    expect($this->store->countSortedSetByScore('test:zset', '15', '25'))->toBe(1);
});

test('removeSortedSetByScore removes matching entries', function () {
    $this->store->addToSortedSet('test:zset', ['a' => 10, 'b' => 20, 'c' => 30]);
    $this->store->removeSortedSetByScore('test:zset', '0', '15');
    expect($this->store->getSortedSetByScore('test:zset', '0', '100'))->toBe(['b', 'c']);
});

test('removeSortedSetByRank removes by position', function () {
    $this->store->addToSortedSet('test:zset', ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
    $this->store->removeSortedSetByRank('test:zset', 0, 1);
    expect($this->store->getSortedSetByScore('test:zset', '0', '100'))->toBe(['c', 'd']);
});

test('removeFromSortedSet removes specific member', function () {
    $this->store->addToSortedSet('test:zset', ['a' => 1, 'b' => 2]);
    $this->store->removeFromSortedSet('test:zset', 'a');
    expect($this->store->getSortedSetByScore('test:zset', '0', '100'))->toBe(['b']);
});

// --- Set operations ---

test('addToSet and getSetMembers', function () {
    $this->store->addToSet('test:set', ['x', 'y', 'z']);
    $members = $this->store->getSetMembers('test:set');
    sort($members);
    expect($members)->toBe(['x', 'y', 'z']);
});

test('addToSet ignores duplicates', function () {
    $this->store->addToSet('test:set', ['x', 'y']);
    $this->store->addToSet('test:set', ['y', 'z']);
    expect($this->store->getSetMembers('test:set'))->toHaveCount(3);
});

test('removeFromSet removes members', function () {
    $this->store->addToSet('test:set', ['a', 'b', 'c']);
    $this->store->removeFromSet('test:set', ['b']);
    $members = $this->store->getSetMembers('test:set');
    sort($members);
    expect($members)->toBe(['a', 'c']);
});

// --- Key scanning ---

test('scanKeys finds matching keys', function () {
    $this->store->set('prefix:a', '1');
    $this->store->set('prefix:b', '2');
    $this->store->set('other:c', '3');
    $keys = $this->store->scanKeys('prefix:*');
    sort($keys);
    expect($keys)->toBe(['prefix:a', 'prefix:b']);
});

// --- Transaction ---

test('transaction wraps operations atomically', function () {
    $this->store->transaction(function () {
        $this->store->set('tx:a', '1');
        $this->store->set('tx:b', '2');
    });
    expect($this->store->get('tx:a'))->toBe('1');
    expect($this->store->get('tx:b'))->toBe('2');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Support/DatabaseMetricsStoreTest.php`
Expected: FAIL — `DatabaseMetricsStore` class not found.

- [ ] **Step 3: Implement DatabaseMetricsStore**

Create `src/Support/DatabaseMetricsStore.php`. This class must implement the same public method signatures as `RedisMetricsStore`. Read `src/Support/RedisMetricsStore.php` for the complete method list.

Key implementation patterns:

```php
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMetrics\Support;

use Cbox\LaravelQueueMetrics\Models\MetricsHash;
use Cbox\LaravelQueueMetrics\Models\MetricsKey;
use Cbox\LaravelQueueMetrics\Models\MetricsSet;
use Cbox\LaravelQueueMetrics\Models\MetricsSortedSet;
use Illuminate\Support\Facades\DB;

class DatabaseMetricsStore
{
    private ?string $prefix = null;

    public function getPrefix(): string
    {
        if ($this->prefix === null) {
            $this->prefix = config('queue-metrics.storage.prefix', 'queue_metrics');
        }

        return $this->prefix;
    }

    public function key(string ...$segments): string
    {
        return implode(':', $segments);
    }

    public function getTtl(string $type): int
    {
        return (int) config("queue-metrics.storage.ttl.{$type}", 3600);
    }

    public function driver(): self
    {
        return $this;
    }

    public function connection(): self
    {
        return $this;
    }

    // --- String operations ---

    public function set(string $key, mixed $value, ?int $ttl = null): void
    {
        MetricsKey::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_string($value) ? $value : json_encode($value),
                'expires_at' => $ttl !== null ? now()->addSeconds($ttl) : null,
                'updated_at' => now(),
            ]
        );
    }

    public function get(string $key): mixed
    {
        $record = MetricsKey::notExpired()->find($key);

        return $record?->value;
    }

    public function exists(string $key): bool
    {
        return MetricsKey::notExpired()->where('key', $key)->exists();
    }

    public function delete(array|string $keys): int
    {
        $keys = is_array($keys) ? $keys : [$keys];

        $count = 0;
        $count += MetricsKey::whereIn('key', $keys)->delete();
        $count += MetricsHash::whereIn('key', $keys)->delete();
        $count += MetricsSet::whereIn('key', $keys)->delete();
        $count += MetricsSortedSet::whereIn('key', $keys)->delete();

        return $count;
    }

    public function expire(string $key, int $seconds): bool
    {
        $expiresAt = now()->addSeconds($seconds);

        $affected = 0;
        $affected += MetricsKey::where('key', $key)->update(['expires_at' => $expiresAt]);
        $affected += MetricsHash::where('key', $key)->update(['expires_at' => $expiresAt]);
        $affected += MetricsSortedSet::where('key', $key)->update(['expires_at' => $expiresAt]);

        return $affected > 0;
    }

    // --- Hash operations ---

    public function setHash(string $key, array $data, ?int $ttl = null): void
    {
        $existing = MetricsHash::find($key);
        $merged = $existing ? array_merge($existing->data, $data) : $data;

        MetricsHash::updateOrCreate(
            ['key' => $key],
            [
                'data' => $merged,
                'expires_at' => $ttl !== null ? now()->addSeconds($ttl) : ($existing?->expires_at),
                'updated_at' => now(),
            ]
        );
    }

    public function getHash(string $key): array
    {
        $record = MetricsHash::notExpired()->find($key);

        return $record?->data ?? [];
    }

    public function getHashField(string $key, string $field): mixed
    {
        $data = $this->getHash($key);

        return $data[$field] ?? null;
    }

    public function incrementHashField(string $key, string $field, int|float $value): void
    {
        DB::transaction(function () use ($key, $field, $value) {
            $record = MetricsHash::lockForUpdate()->find($key);

            $data = $record?->data ?? [];
            $current = $data[$field] ?? 0;
            $data[$field] = is_float($value) ? (float) $current + $value : (int) $current + $value;

            MetricsHash::updateOrCreate(
                ['key' => $key],
                ['data' => $data, 'updated_at' => now()]
            );
        });
    }

    // --- Sorted set operations ---

    public function addToSortedSet(string $key, array $membersWithScores, ?int $ttl = null): void
    {
        $expiresAt = $ttl !== null ? now()->addSeconds($ttl) : null;

        foreach ($membersWithScores as $member => $score) {
            MetricsSortedSet::updateOrCreate(
                ['key' => $key, 'member' => (string) $member],
                [
                    'score' => $score,
                    'expires_at' => $expiresAt,
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function getSortedSetByScore(string $key, string $min, string $max): array
    {
        return MetricsSortedSet::notExpired()
            ->where('key', $key)
            ->whereBetween('score', [(float) $min, (float) $max])
            ->orderBy('score')
            ->pluck('member')
            ->all();
    }

    public function getSortedSetByRank(string $key, int $start, int $stop): array
    {
        $limit = $stop - $start + 1;

        return MetricsSortedSet::notExpired()
            ->where('key', $key)
            ->orderBy('score')
            ->offset($start)
            ->limit($limit)
            ->pluck('member')
            ->all();
    }

    public function countSortedSetByScore(string $key, string $min, string $max): int
    {
        return MetricsSortedSet::notExpired()
            ->where('key', $key)
            ->whereBetween('score', [(float) $min, (float) $max])
            ->count();
    }

    public function removeSortedSetByScore(string $key, string $min, string $max): int
    {
        return MetricsSortedSet::where('key', $key)
            ->whereBetween('score', [(float) $min, (float) $max])
            ->delete();
    }

    public function removeSortedSetByRank(string $key, int $start, int $stop): int
    {
        $members = $this->getSortedSetByRank($key, $start, $stop);

        if (empty($members)) {
            return 0;
        }

        return MetricsSortedSet::where('key', $key)
            ->whereIn('member', $members)
            ->delete();
    }

    public function removeFromSortedSet(string $key, string $member): void
    {
        MetricsSortedSet::where('key', $key)->where('member', $member)->delete();
    }

    // --- Set operations ---

    public function addToSet(string $key, array $members): void
    {
        foreach ($members as $member) {
            MetricsSet::firstOrCreate(
                ['key' => $key, 'member' => (string) $member],
                ['created_at' => now()]
            );
        }
    }

    public function getSetMembers(string $key): array
    {
        return MetricsSet::where('key', $key)->pluck('member')->all();
    }

    public function removeFromSet(string $key, array $members): void
    {
        MetricsSet::where('key', $key)->whereIn('member', $members)->delete();
    }

    // --- Key scanning ---

    public function scanKeys(string $pattern): array
    {
        $sqlPattern = str_replace(['*', '?'], ['%', '_'], $pattern);

        return MetricsKey::notExpired()
            ->where('key', 'like', $sqlPattern)
            ->pluck('key')
            ->all();
    }

    // --- Transaction/Pipeline ---

    public function pipeline(callable $callback): void
    {
        DB::transaction(function () use ($callback) {
            $callback($this);
        });
    }

    public function transaction(callable $callback): array
    {
        $result = [];
        DB::transaction(function () use ($callback, &$result) {
            $result = (array) $callback($this);
        });

        return $result;
    }

    // --- Lua script replacement (no-op for database) ---

    public function eval(string $script, int $numKeys, ...$args): mixed
    {
        // Lua scripts are not supported in database driver.
        // Repository implementations use DB::transaction instead.
        return null;
    }

    public function command(string $method, array $parameters = []): mixed
    {
        // Generic command passthrough is not supported in database driver.
        return null;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Support/DatabaseMetricsStoreTest.php`
Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/DatabaseMetricsStore.php tests/Feature/Support/DatabaseMetricsStoreTest.php
git commit -m "feat: add DatabaseMetricsStore with Eloquent-based KV operations"
```

---

### Task 3: DatabaseWorkerRepository

**Files:**
- Create: `src/Repositories/DatabaseWorkerRepository.php`
- Create: `tests/Feature/Repositories/DatabaseWorkerRepositoryTest.php`
- Reference: `src/Repositories/RedisWorkerRepository.php`
- Reference: `src/Repositories/Contracts/WorkerRepository.php`

- [ ] **Step 1: Write tests**

Read the contract `src/Repositories/Contracts/WorkerRepository.php` for method signatures. Write tests covering: `registerWorker`, `updateWorkerActivity`, `unregisterWorker`, `getWorkerStats`, `getActiveWorkers`, `countActiveWorkers`, `cleanupStaleWorkers`.

Test structure:
```php
<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Repositories\DatabaseWorkerRepository;
use Cbox\LaravelQueueMetrics\Support\DatabaseMetricsStore;

beforeEach(function () {
    $this->store = new DatabaseMetricsStore();
    $this->repo = new DatabaseWorkerRepository($this->store);
});

test('register and retrieve worker', function () {
    $this->repo->registerWorker(123, 'prod-01', 'redis', 'default', now());
    $stats = $this->repo->getWorkerStats(123, 'prod-01');
    expect($stats)->not->toBeNull();
    expect($stats->pid)->toBe(123);
    expect($stats->hostname)->toBe('prod-01');
});

test('unregister removes worker', function () {
    $this->repo->registerWorker(123, 'prod-01', 'redis', 'default', now());
    $this->repo->unregisterWorker(123, 'prod-01');
    expect($this->repo->getWorkerStats(123, 'prod-01'))->toBeNull();
});

test('getActiveWorkers filters by connection and queue', function () {
    $this->repo->registerWorker(1, 'host', 'redis', 'default', now());
    $this->repo->registerWorker(2, 'host', 'redis', 'emails', now());
    $workers = $this->repo->getActiveWorkers('redis', 'default');
    expect($workers)->toHaveCount(1);
});

test('countActiveWorkers returns count', function () {
    $this->repo->registerWorker(1, 'host', 'redis', 'default', now());
    $this->repo->registerWorker(2, 'host', 'redis', 'default', now());
    expect($this->repo->countActiveWorkers('redis', 'default'))->toBe(2);
});

test('cleanupStaleWorkers removes old workers', function () {
    $this->repo->registerWorker(1, 'host', 'redis', 'default', now()->subMinutes(5));
    $this->repo->registerWorker(2, 'host', 'redis', 'default', now());
    $removed = $this->repo->cleanupStaleWorkers(60);
    expect($removed)->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/Feature/Repositories/DatabaseWorkerRepositoryTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement DatabaseWorkerRepository**

Read `src/Repositories/RedisWorkerRepository.php` completely. Create `src/Repositories/DatabaseWorkerRepository.php` implementing `WorkerRepository` contract. Same constructor pattern:

```php
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMetrics\Repositories;

use Cbox\LaravelQueueMetrics\DataTransferObjects\WorkerStatsData;
use Cbox\LaravelQueueMetrics\Repositories\Contracts\WorkerRepository;
use Cbox\LaravelQueueMetrics\Support\DatabaseMetricsStore;
use Carbon\Carbon;

final class DatabaseWorkerRepository implements WorkerRepository
{
    public function __construct(
        private DatabaseMetricsStore $store,
    ) {}

    // Implement all methods following the same key schema as RedisWorkerRepository:
    // Worker hash key: store->key('worker', "{$hostname}:{$pid}")
    // Active workers set key: store->key('active_workers')
    // Workers index sorted set key: store->key('workers', 'all')
    //
    // Read RedisWorkerRepository for exact field names stored in hashes
    // and the logic for filtering by connection/queue.
}
```

Mirror every method from `RedisWorkerRepository`. Replace `$this->redis` calls with `$this->store` calls. The key schema and hash field names must be identical — the data format is the same, only the backing store changes.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/pest tests/Feature/Repositories/DatabaseWorkerRepositoryTest.php`
Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Repositories/DatabaseWorkerRepository.php tests/Feature/Repositories/DatabaseWorkerRepositoryTest.php
git commit -m "feat: add DatabaseWorkerRepository"
```

---

### Task 4: DatabaseWorkerHeartbeatRepository

**Files:**
- Create: `src/Repositories/DatabaseWorkerHeartbeatRepository.php`
- Create: `tests/Feature/Repositories/DatabaseWorkerHeartbeatRepositoryTest.php`
- Reference: `src/Repositories/RedisWorkerHeartbeatRepository.php`
- Reference: `src/Repositories/Contracts/WorkerHeartbeatRepository.php`

- [ ] **Step 1: Write tests**

Cover: `recordHeartbeat`, `transitionState`, `getWorker`, `getActiveWorkers`, `getWorkersByState`, `detectStaledWorkers`, `removeWorker`, `cleanup`.

Key test — the Lua script replacement:
```php
test('recordHeartbeat creates and updates worker with state transitions', function () {
    $this->repo->recordHeartbeat(
        workerId: 'worker-1',
        connection: 'redis',
        queue: 'default',
        state: WorkerState::IDLE,
        currentJobId: null,
        currentJobClass: null,
        pid: 123,
        hostname: 'prod-01',
    );

    $worker = $this->repo->getWorker('worker-1');
    expect($worker)->not->toBeNull();
    expect($worker->state)->toBe(WorkerState::IDLE);

    // Transition to BUSY
    $this->repo->recordHeartbeat(
        workerId: 'worker-1',
        connection: 'redis',
        queue: 'default',
        state: WorkerState::BUSY,
        currentJobId: 'job-42',
        currentJobClass: 'App\\Jobs\\SendEmail',
        pid: 123,
        hostname: 'prod-01',
    );

    $worker = $this->repo->getWorker('worker-1');
    expect($worker->state)->toBe(WorkerState::BUSY);
    expect($worker->currentJobClass)->toBe('App\\Jobs\\SendEmail');
});
```

- [ ] **Step 2: Run tests to verify they fail**

- [ ] **Step 3: Implement DatabaseWorkerHeartbeatRepository**

Read `src/Repositories/RedisWorkerHeartbeatRepository.php` completely. The Lua script `UpdateWorkerHeartbeat.lua` must be replaced with a `DB::transaction()` block that does the same state transition logic: read existing hash → apply state changes → update hash + sorted set index.

```php
public function recordHeartbeat(/* params */): void
{
    DB::transaction(function () use (/* params */) {
        $key = $this->store->key('worker', $workerId);
        $existing = $this->store->getHash($key);

        // Apply state transition logic from UpdateWorkerHeartbeat.lua:
        // - Track idle_since / busy_since timestamps
        // - Increment jobs_processed counter on BUSY→IDLE transition
        // - Track peak memory
        // - Update last_heartbeat

        $this->store->setHash($key, $updated, $this->store->getTtl('raw'));
        $this->store->addToSortedSet(
            $this->store->key('workers', 'all'),
            [$workerId => now()->timestamp]
        );
    });
}
```

- [ ] **Step 4: Run tests to verify they pass**
- [ ] **Step 5: Commit**

```bash
git add src/Repositories/DatabaseWorkerHeartbeatRepository.php tests/Feature/Repositories/DatabaseWorkerHeartbeatRepositoryTest.php
git commit -m "feat: add DatabaseWorkerHeartbeatRepository with transaction-based state management"
```

---

### Task 5: DatabaseJobMetricsRepository

**Files:**
- Create: `src/Repositories/DatabaseJobMetricsRepository.php`
- Create: `tests/Feature/Repositories/DatabaseJobMetricsRepositoryTest.php`
- Reference: `src/Repositories/RedisJobMetricsRepository.php` (largest — 23KB)
- Reference: `src/Repositories/Contracts/JobMetricsRepository.php`

- [ ] **Step 1: Write tests**

This is the most complex repository (17 methods). Cover the critical paths: `recordStart`, `recordCompletion`, `recordFailure`, `getMetrics`, `getDurationSamples`, `getThroughput`, `getAverageDurationInWindow`, `listJobs`, `cleanup`.

```php
test('recordCompletion stores metrics and samples', function () {
    $this->repo->recordCompletion(
        jobId: 'job-1',
        jobClass: 'App\\Jobs\\SendEmail',
        connection: 'redis',
        queue: 'default',
        durationMs: 150.0,
        memoryMb: 32.5,
        cpuTimeMs: 45.0,
        completedAt: now(),
    );

    $metrics = $this->repo->getMetrics('App\\Jobs\\SendEmail', 'redis', 'default');
    expect($metrics['total_processed'])->toBe(1);
    expect((float) $metrics['total_duration_ms'])->toBe(150.0);

    $durations = $this->repo->getDurationSamples('App\\Jobs\\SendEmail', 'redis', 'default', 100);
    expect($durations)->toHaveCount(1);
    expect($durations[0])->toBe(150.0);
});

test('getThroughput counts completions within window', function () {
    $this->repo->recordCompletion('j1', 'Job', 'redis', 'default', 100, 10, 5, now());
    $this->repo->recordCompletion('j2', 'Job', 'redis', 'default', 100, 10, 5, now());
    $this->repo->recordCompletion('j3', 'Job', 'redis', 'default', 100, 10, 5, now()->subHours(2));

    expect($this->repo->getThroughput('Job', 'redis', 'default', 3600))->toBe(2);
});
```

- [ ] **Step 2: Run tests to verify they fail**
- [ ] **Step 3: Implement DatabaseJobMetricsRepository**

Read `src/Repositories/RedisJobMetricsRepository.php` completely. Port all methods. Key patterns:

- `recordCompletion`: atomically increment hash counters (`total_processed`, `total_duration_ms`, etc.) + add samples to sorted sets (score = timestamp)
- `getThroughput`: count sorted set members within time window (replaces Lua script with `countSortedSetByScore`)
- `getAverageDurationInWindow`: get samples within window, compute average (replaces Lua script)
- `getDurationSamples`: `getSortedSetByRank` with limit from config `max_samples_per_key`

- [ ] **Step 4: Run tests to verify they pass**
- [ ] **Step 5: Commit**

```bash
git add src/Repositories/DatabaseJobMetricsRepository.php tests/Feature/Repositories/DatabaseJobMetricsRepositoryTest.php
git commit -m "feat: add DatabaseJobMetricsRepository"
```

---

### Task 6: DatabaseQueueMetricsRepository

**Files:**
- Create: `src/Repositories/DatabaseQueueMetricsRepository.php`
- Create: `tests/Feature/Repositories/DatabaseQueueMetricsRepositoryTest.php`
- Reference: `src/Repositories/RedisQueueMetricsRepository.php`
- Reference: `src/Repositories/Contracts/QueueMetricsRepository.php`

- [ ] **Step 1: Write tests**

Cover: `recordSnapshot`, `getLatestMetrics`, `getQueueState`, `getHealthStatus`, `listQueues`, `markQueueDiscovered`, `cleanup`.

- [ ] **Step 2: Run tests to verify they fail**
- [ ] **Step 3: Implement DatabaseQueueMetricsRepository**

Read `src/Repositories/RedisQueueMetricsRepository.php`. Port all methods. Key differences from Redis:
- `recordSnapshot`: upsert hash + add to sorted set (capped history)
- `getHealthStatus`: uses string key via `set`/`get`
- `listQueues`: reads from discovery set
- `cleanup`: `scanKeys` to find matching keys + delete

- [ ] **Step 4: Run tests to verify they pass**
- [ ] **Step 5: Commit**

```bash
git add src/Repositories/DatabaseQueueMetricsRepository.php tests/Feature/Repositories/DatabaseQueueMetricsRepositoryTest.php
git commit -m "feat: add DatabaseQueueMetricsRepository"
```

---

### Task 7: DatabaseBaselineRepository

**Files:**
- Create: `src/Repositories/DatabaseBaselineRepository.php`
- Create: `tests/Feature/Repositories/DatabaseBaselineRepositoryTest.php`
- Reference: `src/Repositories/RedisBaselineRepository.php`
- Reference: `src/Repositories/Contracts/BaselineRepository.php`

- [ ] **Step 1: Write tests**

Cover: `storeBaseline`, `getBaseline`, `getBaselines`, `getJobClassBaseline`, `getJobClassBaselines`, `hasRecentBaseline`, `deleteBaseline`, `cleanup`.

- [ ] **Step 2: Run tests to verify they fail**
- [ ] **Step 3: Implement DatabaseBaselineRepository**

Read `src/Repositories/RedisBaselineRepository.php`. Port all methods. This is the simplest repository — primarily hash get/set with long TTLs. Key schema:
- Aggregate: `store->key('baseline', $connection, $queue, '_aggregate')`
- Per-class: `store->key('baseline', $connection, $queue, $jobClass)`
- `hasRecentBaseline`: check hash exists + `updated_at` within `maxAgeSeconds`
- `getBaselines`: `scanKeys` for pattern matching then get each hash

- [ ] **Step 4: Run tests to verify they pass**
- [ ] **Step 5: Commit**

```bash
git add src/Repositories/DatabaseBaselineRepository.php tests/Feature/Repositories/DatabaseBaselineRepositoryTest.php
git commit -m "feat: add DatabaseBaselineRepository"
```

---

### Task 8: Cleanup Command

**Files:**
- Create: `src/Console/CleanupDatabaseCommand.php`
- Create: `tests/Feature/Console/CleanupDatabaseCommandTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Models\MetricsHash;
use Cbox\LaravelQueueMetrics\Models\MetricsKey;
use Cbox\LaravelQueueMetrics\Models\MetricsSortedSet;

test('cleanup removes expired keys', function () {
    MetricsKey::create(['key' => 'old', 'value' => 'x', 'expires_at' => now()->subHour(), 'updated_at' => now()]);
    MetricsKey::create(['key' => 'fresh', 'value' => 'x', 'expires_at' => now()->addHour(), 'updated_at' => now()]);

    $this->artisan('queue-metrics:cleanup-database')->assertExitCode(0);

    expect(MetricsKey::count())->toBe(1);
    expect(MetricsKey::first()->key)->toBe('fresh');
});

test('cleanup removes expired hashes', function () {
    MetricsHash::create(['key' => 'old', 'data' => [], 'expires_at' => now()->subHour(), 'updated_at' => now()]);

    $this->artisan('queue-metrics:cleanup-database')->assertExitCode(0);

    expect(MetricsHash::count())->toBe(0);
});

test('cleanup trims sorted sets exceeding max samples', function () {
    $max = config('queue-metrics.storage.max_samples_per_key', 1000);

    for ($i = 0; $i < $max + 50; $i++) {
        MetricsSortedSet::create([
            'key' => 'samples:test',
            'member' => "m-{$i}",
            'score' => $i,
            'updated_at' => now(),
        ]);
    }

    $this->artisan('queue-metrics:cleanup-database')->assertExitCode(0);

    expect(MetricsSortedSet::where('key', 'samples:test')->count())->toBe($max);
});

test('cleanup does nothing when no expired data', function () {
    MetricsKey::create(['key' => 'valid', 'value' => 'x', 'expires_at' => now()->addDay(), 'updated_at' => now()]);

    $this->artisan('queue-metrics:cleanup-database')->assertExitCode(0);

    expect(MetricsKey::count())->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**
- [ ] **Step 3: Implement CleanupDatabaseCommand**

```php
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMetrics\Console;

use Cbox\LaravelQueueMetrics\Models\MetricsHash;
use Cbox\LaravelQueueMetrics\Models\MetricsKey;
use Cbox\LaravelQueueMetrics\Models\MetricsSortedSet;
use Illuminate\Console\Command;

class CleanupDatabaseCommand extends Command
{
    public $signature = 'queue-metrics:cleanup-database';

    public $description = 'Remove expired metrics data from database storage';

    public function handle(): int
    {
        $chunkSize = (int) config('queue-metrics.storage.cleanup_chunk_size', 1000);

        // Delete expired rows
        MetricsKey::expired()->limit($chunkSize)->delete();
        MetricsHash::expired()->limit($chunkSize)->delete();
        MetricsSortedSet::expired()->limit($chunkSize)->delete();

        // Trim sorted sets exceeding max_samples_per_key
        $maxSamples = (int) config('queue-metrics.storage.max_samples_per_key', 1000);

        MetricsSortedSet::query()
            ->select('key')
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('key')
            ->havingRaw('COUNT(*) > ?', [$maxSamples])
            ->each(function ($row) use ($maxSamples) {
                $excess = MetricsSortedSet::where('key', $row->key)
                    ->orderBy('score')
                    ->limit($row->cnt - $maxSamples)
                    ->pluck('member');

                MetricsSortedSet::where('key', $row->key)
                    ->whereIn('member', $excess)
                    ->delete();
            });

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**
- [ ] **Step 5: Commit**

```bash
git add src/Console/CleanupDatabaseCommand.php tests/Feature/Console/CleanupDatabaseCommandTest.php
git commit -m "feat: add database cleanup command for expired metrics and sample trimming"
```

---

### Task 9: Config + Service Provider Wiring

**Files:**
- Modify: `config/queue-metrics.php`
- Modify: `src/LaravelQueueMetricsServiceProvider.php`
- Create: `tests/Feature/DatabaseDriverIntegrationTest.php`

- [ ] **Step 1: Update config**

Add `max_samples_per_key` and `cleanup_chunk_size` to storage section. Change repositories defaults to `null`:

In `config/queue-metrics.php`, change:
```php
'storage' => [
    'driver' => env('QUEUE_METRICS_STORAGE', 'redis'),
    'connection' => env('QUEUE_METRICS_CONNECTION', 'default'),
    'prefix' => 'queue_metrics',
    'ttl' => [
        'raw' => 3600,
        'aggregated' => 604800,
        'baseline' => 2592000,
    ],
],
```

To:
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
```

Change repositories section — set all values to `null`:
```php
'repositories' => [
    JobMetricsRepository::class => null,
    QueueMetricsRepository::class => null,
    WorkerRepository::class => null,
    BaselineRepository::class => null,
    WorkerHeartbeatRepository::class => null,
],
```

- [ ] **Step 2: Update StorageConfig**

Add `maxSamplesPerKey` and `cleanupChunkSize` properties:
```php
public readonly int $maxSamplesPerKey;
public readonly int $cleanupChunkSize;
```

Initialize in `fromArray()`:
```php
$this->maxSamplesPerKey = (int) ($config['max_samples_per_key'] ?? 1000);
$this->cleanupChunkSize = (int) ($config['cleanup_chunk_size'] ?? 1000);
```

- [ ] **Step 3: Update ServiceProvider repository binding**

Replace the existing repository binding loop in `packageRegistered()`. When a repository config value is `null`, resolve from driver:

```php
$driver = config('queue-metrics.storage.driver', 'redis');
$driverDefaults = match ($driver) {
    'database' => [
        JobMetricsRepository::class => DatabaseJobMetricsRepository::class,
        QueueMetricsRepository::class => DatabaseQueueMetricsRepository::class,
        WorkerRepository::class => DatabaseWorkerRepository::class,
        BaselineRepository::class => DatabaseBaselineRepository::class,
        WorkerHeartbeatRepository::class => DatabaseWorkerHeartbeatRepository::class,
    ],
    default => [
        JobMetricsRepository::class => RedisJobMetricsRepository::class,
        QueueMetricsRepository::class => RedisQueueMetricsRepository::class,
        WorkerRepository::class => RedisWorkerRepository::class,
        BaselineRepository::class => RedisBaselineRepository::class,
        WorkerHeartbeatRepository::class => RedisWorkerHeartbeatRepository::class,
    ],
};

$repositories = config('queue-metrics.repositories', []);

foreach ($driverDefaults as $contract => $default) {
    $implementation = $repositories[$contract] ?? $default;
    $this->app->singleton($contract, $implementation);
}

// Bind the appropriate metrics store
if ($driver === 'database') {
    $this->app->singleton(DatabaseMetricsStore::class);
} else {
    $this->app->singleton(RedisMetricsStore::class);
}
```

- [ ] **Step 4: Register cleanup command and schedule**

In the service provider, register the command:
```php
// In configurePackage or boot
$this->app->make(Schedule::class)->command('queue-metrics:cleanup-database')
    ->everyMinute()
    ->when(fn () => config('queue-metrics.storage.driver') === 'database');
```

Register command in the commands list alongside existing commands.

- [ ] **Step 5: Write integration test**

```php
<?php

declare(strict_types=1);

use Cbox\LaravelQueueMetrics\Repositories\Contracts\JobMetricsRepository;
use Cbox\LaravelQueueMetrics\Repositories\Contracts\WorkerRepository;
use Cbox\LaravelQueueMetrics\Repositories\DatabaseJobMetricsRepository;
use Cbox\LaravelQueueMetrics\Repositories\DatabaseWorkerRepository;

test('database driver binds database repositories', function () {
    config()->set('queue-metrics.storage.driver', 'database');
    config()->set('queue-metrics.repositories', [
        JobMetricsRepository::class => null,
        WorkerRepository::class => null,
    ]);

    // Force re-registration
    $provider = new \Cbox\LaravelQueueMetrics\LaravelQueueMetricsServiceProvider(app());
    $provider->packageRegistered();

    expect(app(WorkerRepository::class))->toBeInstanceOf(DatabaseWorkerRepository::class);
    expect(app(JobMetricsRepository::class))->toBeInstanceOf(DatabaseJobMetricsRepository::class);
});

test('explicit repository override takes precedence over driver', function () {
    config()->set('queue-metrics.storage.driver', 'database');
    config()->set('queue-metrics.repositories', [
        WorkerRepository::class => \Cbox\LaravelQueueMetrics\Repositories\RedisWorkerRepository::class,
    ]);

    $provider = new \Cbox\LaravelQueueMetrics\LaravelQueueMetricsServiceProvider(app());
    $provider->packageRegistered();

    expect(app(WorkerRepository::class))->toBeInstanceOf(\Cbox\LaravelQueueMetrics\Repositories\RedisWorkerRepository::class);
});
```

- [ ] **Step 6: Run all tests**

Run: `vendor/bin/pest`
Expected: All PASS.

- [ ] **Step 7: Commit**

```bash
git add config/queue-metrics.php src/Config/StorageConfig.php src/LaravelQueueMetricsServiceProvider.php tests/Feature/DatabaseDriverIntegrationTest.php
git commit -m "feat: wire database driver via config with null-default repository resolution"
```

---

### Task 10: Documentation

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Add database driver section to README**

Add after the existing installation/configuration section:

```markdown
### Storage Drivers

#### Redis (default, recommended)

Redis is the recommended driver for all workloads. It handles high-frequency metrics writes with minimal overhead.

```env
QUEUE_METRICS_STORAGE=redis
QUEUE_METRICS_CONNECTION=default
```

#### Database

For applications without Redis infrastructure, a database driver is available. This stores metrics in 4 database tables using the same schema as your application database.

```env
QUEUE_METRICS_STORAGE=database
QUEUE_METRICS_CONNECTION=mysql  # or your database connection
```

Run the migration to create the metrics tables:

```bash
php artisan migrate
```

> **Important:** The database driver is designed for low-scale workloads (< 10 workers). At higher scale, metrics writes create contention on the same database your queue jobs use. Use Redis for production with moderate to high workloads.

> **Recommended setting for database driver:** Set `QUEUE_METRICS_MAX_SAMPLES=500` to keep table sizes manageable.

The database cleanup command runs automatically every minute when using the database driver, removing expired data and trimming sample tables.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: add database driver documentation with scale recommendations"
```
