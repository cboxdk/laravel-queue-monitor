---
title: "Architecture"
description: "Package architecture with Action, DTO, and Repository patterns explained"
weight: 80
---

# Architecture

## Design Principles

The Laravel Queue Monitor package follows these architectural principles:

1. **Action Pattern** - All business logic in single-responsibility actions
2. **DTO Pattern** - Type-safe data transfer objects for all data movement
3. **Repository Pattern** - Data access abstraction with contracts
4. **Event-Driven** - Loosely coupled integration via events
5. **SOLID Principles** - Maintainable, extensible, testable code

## Package Structure

```
src/
├── Actions/
│   ├── Core/                     # Job lifecycle actions
│   │   ├── RecordJobQueuedAction
│   │   ├── RecordJobStartedAction
│   │   ├── RecordJobCompletedAction
│   │   ├── RecordJobFailedAction
│   │   ├── RecordJobTimeoutAction
│   │   ├── UpdateJobMetricsAction
│   │   ├── CancelJobAction
│   │   └── PruneJobsAction
│   ├── Analytics/                # Analytics calculations
│   │   ├── CalculateJobStatisticsAction
│   │   ├── CalculateServerStatisticsAction
│   │   └── CalculateQueueHealthAction
│   └── Replay/                   # Job replay
│       └── ReplayJobAction
├── Commands/                     # Artisan CLI commands
│   ├── LaravelQueueMonitorCommand (stats)
│   ├── PruneJobsCommand
│   └── ReplayJobCommand
├── DataTransferObjects/          # Type-safe DTOs
│   ├── JobMonitorData
│   ├── WorkerContextData
│   ├── ExceptionData
│   ├── JobFilterData
│   └── JobReplayData
├── Enums/                        # PHP 8.3 enums
│   ├── JobStatus
│   └── WorkerType
├── Events/                       # Package events
│   ├── JobMonitorRecorded
│   ├── JobReplayRequested
│   └── JobCancelled
├── Http/
│   ├── Controllers/              # REST API controllers
│   │   ├── JobMonitorController
│   │   ├── JobReplayController
│   │   ├── StatisticsController
│   │   └── PruneController
│   ├── Middleware/               # API middleware
│   │   └── EnsureQueueMonitorEnabled
│   └── Resources/                # API resources
│       ├── JobMonitorResource
│       ├── JobMonitorCollection
│       └── StatisticsResource
├── Listeners/                    # Event listeners
│   ├── JobQueuedListener
│   ├── JobProcessingListener
│   ├── JobProcessedListener
│   ├── JobFailedListener
│   ├── JobTimedOutListener
│   └── QueueMetricsSubscriber
├── Models/                       # Eloquent models
│   ├── JobMonitor
│   └── Tag
├── Repositories/
│   ├── Contracts/                # Repository interfaces
│   │   ├── JobMonitorRepositoryContract
│   │   ├── TagRepositoryContract
│   │   └── StatisticsRepositoryContract
│   └── Eloquent/                 # Eloquent implementations
│       ├── EloquentJobMonitorRepository
│       ├── EloquentTagRepository
│       └── EloquentStatisticsRepository
├── Services/                     # Domain services
│   └── WorkerContextService
├── Facades/
│   └── LaravelQueueMonitor
└── LaravelQueueMonitorServiceProvider
```

## Data Flow

### Job Lifecycle Tracking

```
1. Job Dispatched
   └→ JobQueued Event
      └→ JobQueuedListener
         └→ RecordJobQueuedAction
            └→ JobMonitorRepository::create()
               └→ DB: INSERT queue_monitor_jobs

2. Job Processing
   └→ JobProcessing Event
      └→ JobProcessingListener
         └→ RecordJobStartedAction
            └→ JobMonitorRepository::update()
               └→ DB: UPDATE status=processing, started_at

3. Job Completed/Failed
   └→ JobProcessed/JobFailed Event
      └→ Listener
         └→ RecordJobCompletedAction/RecordJobFailedAction
            └→ JobMonitorRepository::update()
               └→ DB: UPDATE status, completed_at, metrics
               └→ TagRepository::storeTags()

4. Metrics Enrichment (async)
   └→ MetricsRecorded Event (from laravel-queue-metrics)
      └→ QueueMetricsSubscriber
         └→ UpdateJobMetricsAction
            └→ JobMonitorRepository::update()
               └→ DB: UPDATE cpu_time_ms, memory_peak_mb
```

### Job Replay Flow

```
1. Replay Request
   └→ QueueMonitor::replay($uuid) or API POST
      └→ ReplayJobAction::execute()
         ├→ Validate job exists
         ├→ Validate payload stored
         ├→ Validate job class exists
         ├→ Validate not processing
         └→ Queue::pushRaw(payload, queue)
            └→ Triggers normal job lifecycle
               └→ JobReplayRequested Event
```

## Component Responsibilities

### Actions
Single-responsibility business logic units. Each action:
- Accepts specific input (event, DTO, parameters)
- Performs one clear operation
- Returns result or throws exception
- Checks config enablement
- Handles errors gracefully (monitoring shouldn't break queues)

### Repositories
Data access layer. Each repository:
- Implements contract interface
- Encapsulates query logic
- Returns Eloquent models or collections
- Handles database transactions
- Provides domain-specific queries

### DTOs
Immutable data carriers. Each DTO:
- Uses readonly properties
- Provides fromArray() constructor
- Provides toArray() serialization
- Contains no business logic
- Ensures type safety

### Services
Domain services for complex operations. Each service:
- Coordinates multiple components
- Provides high-level operations
- Encapsulates domain knowledge
- Stateless operations

## Extensibility Points

### Custom Repositories

Override default repository implementations:

```php
// config/queue-monitor.php
'repositories' => [
    JobMonitorRepositoryContract::class => CustomJobMonitorRepository::class,
],
```

### Custom Actions

Replace action implementations:

```php
'actions' => [
    'replay_job' => CustomReplayJobAction::class,
],
```

### Custom Server Name Detection

Provide custom callable for server identification:

```php
'worker_detection' => [
    'server_name_callable' => function() {
        return config('app.server_identifier');
    },
],
```

### Event Hooks

Subscribe to package events:

```php
Event::listen(JobReplayRequested::class, function($event) {
    Log::info("Job replayed: {$event->originalJob->uuid}");
});
```

## Integration Points

### Laravel Queue Events
- **JobQueued** - When job is pushed to queue
- **JobProcessing** - When worker picks up job
- **JobProcessed** - When job completes successfully
- **JobFailed** - When job fails with exception
- **JobTimedOut** - When job exceeds timeout

### Queue-Metrics Events
- **MetricsRecorded** - When queue-metrics records job metrics
  - Provides CPU time, memory usage, file descriptors
  - Automatically enriches job monitor records

### Package Events
- **JobMonitorRecorded** - When job record created/updated
- **JobReplayRequested** - When job replay is initiated
- **JobCancelled** - When job is manually cancelled

## Database Schema

### queue_monitor_jobs Table

**Primary Keys & Identification:**
- `id` - Auto-increment primary key
- `uuid` - Job UUID for tracking across retries
- `job_id` - Laravel's internal job ID

**Indexes:**
- Single: uuid, job_id, job_class, queue, status, duration_ms
- Composite: (status, created_at), (queue, status, created_at), (job_class, status)

**Foreign Keys:**
- `retried_from_id` → `queue_monitor_jobs.id` (nullable, on delete null)

### queue_monitor_tags Table

**Structure:**
- `id` - Primary key
- `job_id` - Foreign key to queue_monitor_jobs (cascade on delete)
- `tag` - Tag value (indexed)
- Unique constraint on (job_id, tag)

## Performance Considerations

### Query Optimization
- Strategic indexes for common queries
- Composite indexes for multi-column filters
- Nullable fields to reduce storage
- JSON column for flexible tag storage

### Resource Usage
- Configurable payload storage (can be disabled)
- Automatic pruning of old records
- Efficient bulk operations
- Minimal overhead on queue processing

### Scalability
- Works with any Laravel queue driver
- Supports multi-server deployments
- Handles high-throughput scenarios
- Database connection can be isolated

## Testing Architecture

### Test Organization
```
tests/
├── Unit/                         # Isolated unit tests
│   ├── Enums/
│   ├── DataTransferObjects/
│   └── Models/
├── Feature/                      # Integration tests
│   ├── Actions/
│   ├── Api/
│   ├── Commands/
│   └── Repositories/
└── Pest.php                      # Test configuration
```

### Test Database
- Uses SQLite in-memory for speed
- RefreshDatabase trait for isolation
- Factory pattern for test data
- Custom Pest expectations

## Code Quality Standards

- **PHPStan Level 9** - Maximum static analysis
- **declare(strict_types=1)** - Strict typing on all files
- **PHP 8.3+** - Modern PHP features (readonly, enums)
- **Type Hints** - Full type coverage
- **DocBlocks** - Generic type annotations
- **Final Classes** - Prevent inheritance where appropriate
- **Readonly Properties** - Immutability by default
