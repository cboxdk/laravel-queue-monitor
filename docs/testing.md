---
title: "Testing Guide"
description: "Write comprehensive tests for queue monitoring with Pest 4 framework"
weight: 75
---

# Testing Guide

## Running Tests

### All Tests

```bash
composer test
```

### With Coverage

```bash
composer test-coverage
```

### Specific Test Suite

```bash
vendor/bin/pest tests/Unit
vendor/bin/pest tests/Feature
```

### Specific Test File

```bash
vendor/bin/pest tests/Unit/Enums/JobStatusTest.php
```

## Test Organization

```
tests/
├── Unit/                          # Isolated unit tests
│   ├── Enums/                    # Enum behavior tests
│   ├── DataTransferObjects/      # DTO serialization tests
│   └── Models/                   # Model behavior tests
├── Feature/                       # Integration tests
│   ├── Actions/                  # Action execution tests
│   ├── Api/                      # API endpoint tests
│   ├── Commands/                 # Artisan command tests
│   └── Repositories/             # Repository query tests
└── Pest.php                       # Global test configuration
```

## Test Database

Tests use SQLite in-memory database for speed:

```php
config()->set('database.connections.testing', [
    'driver' => 'sqlite',
    'database' => ':memory:',
]);
```

Migrations run automatically via `defineDatabaseMigrations()`.

## Using Factories

### Create Test Jobs

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

// Default completed job
$job = JobMonitor::factory()->create();

// Failed job
$failed = JobMonitor::factory()->failed()->create();

// Processing job
$processing = JobMonitor::factory()->processing()->create();

// Queued job
$queued = JobMonitor::factory()->queued()->create();

// Horizon worker
$horizonJob = JobMonitor::factory()->horizon()->create();

// Slow job
$slow = JobMonitor::factory()->slow(10000)->create(); // 10 seconds

// With tags
$tagged = JobMonitor::factory()->withTags(['priority', 'email'])->create();

// Multiple jobs
$jobs = JobMonitor::factory()->count(10)->create();
```

### Custom Attributes

```php
$job = JobMonitor::factory()->create([
    'queue' => 'emails',
    'server_name' => 'web-1',
    'duration_ms' => 5000,
]);
```

## Writing Tests

### Unit Tests

Test isolated components:

```php
use Cbox\LaravelQueueMonitor\Enums\JobStatus;

test('isFinished returns true for completed status', function () {
    expect(JobStatus::COMPLETED->isFinished())->toBeTrue();
});

test('DTO converts to array correctly', function () {
    $data = new WorkerContextData(
        serverName: 'web-1',
        workerId: 'worker-123',
        workerType: WorkerType::QUEUE_WORK
    );

    $array = $data->toArray();

    expect($array['server_name'])->toBe('web-1');
    expect($array['worker_type'])->toBe('queue_work');
});
```

### Feature Tests

Test integrated functionality:

```php
test('can list jobs via API', function () {
    JobMonitor::factory()->count(3)->create();

    $response = $this->getJson('/api/queue-monitor/jobs');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('replay action dispatches job to queue', function () {
    Queue::fake();

    $job = JobMonitor::factory()->failed()->create();
    $action = app(ReplayJobAction::class);

    $action->execute($job->uuid);

    Queue::assertPushedOn($job->queue);
});
```

### Testing Actions

```php
use Cbox\LaravelQueueMonitor\Actions\Core\RecordJobCompletedAction;

test('record completed action updates job status', function () {
    $job = JobMonitor::factory()->processing()->create();

    $event = new \Illuminate\Queue\Events\JobProcessed(
        'redis',
        new MockJob($job->job_id)
    );

    $action = app(RecordJobCompletedAction::class);
    $action->execute($event);

    $job->refresh();

    expect($job->status)->toBe(JobStatus::COMPLETED);
    expect($job->completed_at)->not->toBeNull();
    expect($job->duration_ms)->toBeInt();
});
```

### Testing Repositories

```php
test('repository filters jobs by status', function () {
    JobMonitor::factory()->count(5)->create(['status' => JobStatus::COMPLETED]);
    JobMonitor::factory()->count(3)->failed()->create();

    $repository = app(JobMonitorRepositoryContract::class);
    $filters = new JobFilterData(statuses: [JobStatus::FAILED]);

    $results = $repository->query($filters);

    expect($results)->toHaveCount(3);
    expect($results->every(fn($job) => $job->isFailed()))->toBeTrue();
});
```

### Testing API Endpoints

```php
test('statistics endpoint returns correct structure', function () {
    JobMonitor::factory()->count(10)->create();
    JobMonitor::factory()->count(2)->failed()->create();

    $response = $this->getJson('/api/queue-monitor/statistics');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total',
                'completed',
                'failed',
                'success_rate',
            ],
        ]);

    expect($response->json('data.total'))->toBe(12);
});
```

## Custom Expectations

The package provides custom Pest expectations:

```php
// Check if value is a specific job status
expect($job->status)->toBeJobStatus('completed');

// Check if job has any metrics
expect($job)->toHaveMetrics();
```

## Testing Best Practices

### 1. Arrange-Act-Assert Pattern

```php
test('example test', function () {
    // Arrange
    $job = JobMonitor::factory()->create();

    // Act
    $result = QueueMonitor::getJob($job->uuid);

    // Assert
    expect($result)->not->toBeNull();
    expect($result->uuid)->toBe($job->uuid);
});
```

### 2. Use Factories

```php
// Good
$job = JobMonitor::factory()->failed()->create();

// Avoid
$job = JobMonitor::create([
    'uuid' => Str::uuid(),
    'job_class' => 'App\\Jobs\\TestJob',
    // ... 20 more fields
]);
```

### 3. Test Edge Cases

```php
test('replay throws exception when payload missing', function () {
    $job = JobMonitor::factory()->create(['payload' => null]);
    $action = app(ReplayJobAction::class);

    $action->execute($job->uuid);
})->throws(RuntimeException::class, 'payload not stored');
```

### 4. Use Pest's Higher Order Testing

```php
test('completed jobs are finished')
    ->with([
        JobStatus::COMPLETED,
        JobStatus::FAILED,
        JobStatus::TIMEOUT,
    ])
    ->expect(fn($status) => $status->isFinished())
    ->toBeTrue();
```

## Mocking

### Queue Facade

```php
use Illuminate\Support\Facades\Queue;

test('replay dispatches job', function () {
    Queue::fake();

    $job = JobMonitor::factory()->create();
    QueueMonitor::replay($job->uuid);

    Queue::assertPushedOn($job->queue);
});
```

### Events

```php
use Illuminate\Support\Facades\Event;

test('job replay fires event', function () {
    Event::fake();

    $job = JobMonitor::factory()->create();
    QueueMonitor::replay($job->uuid);

    Event::assertDispatched(JobReplayRequested::class);
});
```

## CI/CD Integration

### GitHub Actions

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, sqlite3
      - run: composer install
      - run: composer test
      - run: composer analyse
```

## Coverage Requirements

Aim for:
- **Overall**: >90% coverage
- **Actions**: 100% coverage (critical business logic)
- **Repositories**: >95% coverage
- **DTOs**: 100% coverage
- **API Controllers**: >90% coverage

Run with coverage reporting:

```bash
composer test-coverage
```
