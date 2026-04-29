# Changelog

All notable changes to `laravel-queue-monitor` will be documented in this file.

## v1.7.0 - 2026-04-29

### Changes

- Bump `cboxdk/laravel-queue-metrics` dependency to `^3.0`

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.6.1...v1.7.0

## v1.6.1 - 2026-04-29

### Fixes

- **Fallback hosts panel**: Show active managers from database events when live cluster data (Redis) is unavailable, so hosts are visible even without the autoscale package in the monitor context.
- **Capacity fallback**: Capacity field in cluster topology now falls back to live worker counts when no scaling signals have been recorded yet.

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.6.0...v1.6.1

## v1.6.0 - 2026-04-29

### Features

- **Autoscale v3 support**: Full cluster orchestration monitoring with `ClusterEvent` model, v3 event handlers with signal sampling, and all 11 autoscale v3 events registered in the service provider.
- **Cluster UI**: New Autoscale tab with cluster banner, sparkline, timeline, enhanced SLA view, live host resources/workloads, expandable host detail with per-queue worker breakdown, and auto-refresh.
- **Two-stage event pruning**: Payload + row TTL pruning via `PruneEventsAction` for managing cluster event storage.
- **Job debounce listener**: New `JobDebouncedListener` for autoscale v3 debounced events.
- **Persistent filters**: Job filters and pagination state now persist in URL query params.

### Fixes

- **Replay dispatching**: Assign fresh UUID and ID to payload so replayed jobs actually dispatch.
- **Delete job route**: Use POST route instead of DELETE method for job deletion.
- **Job class resolution**: Resolve actual job class from payload when `displayName` is null.
- **Failure pattern UI**: Fix click race condition and search in failure patterns.
- **Dashboard routing**: Route all dashboard mutations (replay, batch, delete) through web middleware.
- **Auth error handling**: Catch exceptions in auth callback to prevent 500 errors.
- **Migration conflicts**: Fix duplicate migration loading on Laravel 11 and index conflicts.
- **V3 migration ordering**: Rename v3 migration to fix alphabetical ordering issues.
- **NOT NULL constraints**: Fix constraint violations, null-safe access, and Alpine.js debounce issues.
- **Host panel**: Show real hostname and CPU cores instead of placeholder values.
- **Autoscale tab restore**: Fix tab not restoring on hard refresh.
- **Background gradient**: Fix gradient not covering full page height.

### Changes

- Rename Infrastructure tab to Horizon and hide when Horizon is not installed.

### Dependencies

- Update `orchestra/testbench` requirement to `^11.1.0`.

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.5.1...v1.6.0

## v1.5.1 - 2026-04-22

### Fixes

- **Dashboard cache invalidation**: Refresh cached dashboard statistics and queue filters when jobs are created, updated, deleted, pruned, or enriched with queue-metrics data.
- **Metrics freshness**: Scope dashboard cache keys with a versioned cache service so overview widgets and available queue filters stop serving stale values after queue activity.
- **Exception tracking updates**: Bust dashboard caches when `JobExceptionOccurred` stores exception details, so failed attempts are reflected immediately in the UI.
- **CI compatibility**: Fix listener tests to resolve `JobExceptionOccurredListener` through the container after constructor dependency injection was added.

### Documentation

- Add a dedicated authentication and access control guide for protecting the dashboard and API.
- Clarify that `LaravelQueueMonitor::auth()` can be registered from `AuthServiceProvider` or any other application service provider.

### Dependencies

- Bump `dependabot/fetch-metadata` from `3.0.0` to `3.1.0`

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.5.0...v1.5.1

## v1.5.0 - 2026-04-15

### What's Changed

* feat: color-coded CPU/memory utilization metrics by @sylvesterdamgaard in https://github.com/cboxdk/laravel-queue-monitor/pull/13

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.4.0...v1.5.0

## v1.4.0 - 2026-04-10

### What's Changed

* feat: add resolve actions for stuck jobs on health page by @sylvesterdamgaard in https://github.com/cboxdk/laravel-queue-monitor/pull/12

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.3.1...v1.4.0

## v1.3.1 - 2026-04-10

### Bug Fixes

- **MySQL ONLY_FULL_GROUP_BY compatibility**: Remove non-aggregated `pickup_seconds` from grouped SELECT in `getSlaData()` -- MySQL strict mode (default since 5.7.5) rejected the query with error 1055. The expression was dead code.
- **Queue Backlog always showing 0**: Add `queue_backlog` to global statistics API response so the dashboard card displays the actual count of queued jobs.

### Refactoring

- **Remove duplicate stat keys**: Global statistics no longer returns redundant `_jobs` suffixed aliases (`total_jobs`, `completed_jobs`, `failed_jobs`, `timeout_jobs`, `processing_jobs`). Use the short names (`total`, `completed`, `failed`, `timeout`, `processing`) instead.

### Dependencies

- Bump `dependabot/fetch-metadata` from 2.5.0 to 3.0.0

## v1.3.0 - 2026-04-09

### What's Changed

#### Fixes

- **Input validation**: Bound `failed()` and `recent()` API limit to 1-1000 (was unbounded)
- **Error reporting**: Silent catch blocks now call `report($e)` in `BatchDeleteAction` and `RecordJobQueuedAction`
- **Query safety**: Replace fragile `addBinding()` with inline `selectRaw()` bindings in drill-down controller
- **Code quality**: Extract `JobMonitorTransformer` to consolidate duplicated response mapping (~90 lines removed)

#### Documentation

- Document metrics storage setup: Redis (default) vs database driver
- Remove misleading "No Redis required" — Redis is default for metrics, database is opt-in for low-scale
- Installation guide with step-by-step metrics storage configuration
- Performance guidance: database driver for < 10 workers

#### Testing

- 16 new test files, 103 new tests
- Coverage: 65.9% → 78.6%
- 391 tests, 1056 assertions

#### Compatibility

- Supports `cboxdk/laravel-queue-metrics` v2.5.0 database driver (existing `^2.3` constraint)

## v1.2.0-beta.8 - 2026-04-08

### Papercut Fixes & Test Coverage

#### Fixes

- **Input validation**: Bound `failed()` and `recent()` API limit parameter to 1-1000 (was unbounded)
- **Error reporting**: Add `report($e)` to silent catch blocks in `BatchDeleteAction` and `RecordJobQueuedAction`
- **Query safety**: Replace fragile `addBinding()` with inline `selectRaw()` bindings in `DashboardDrillDownController`
- **Transformer extraction**: Create `JobMonitorTransformer` to consolidate duplicated job-to-array mapping (~90 lines removed)
- **Cleanup**: Remove duplicate variable assignment in `DashboardDrillDownController`

#### Testing

- 16 new test files, 103 new tests
- Test coverage: 65.9% → 78.6%
- 391 tests passing, 1056 assertions

## v1.2.0 - 2026-04-07

### What's New

#### Web Dashboard

- Full dashboard redesign with 5-tab monitoring hub (overview, jobs, analytics, health, infrastructure)
- Full-page job detail with drill-down views and deep-linkable URLs
- Attempts trail and retry chain visualization

#### Terminal Dashboard (TUI)

- Complete rebuild with k9s-inspired compact design
- Arrow key navigation, status/queue filters, search
- Feature parity with the web dashboard
- Memory utilization shown as percentage of worker limit
- CPU % and memory columns in job list tables

#### Performance

- Added missing database indexes, fixed N+1 queries
- Health check caching and prune validation
- Batch keypresses and cached data for instant TUI navigation

#### Bug Fixes

- Fixed Redis job serialization for queue job wrappers
- Fixed TUI ghost rendering on view switch
- Fixed CPU display (percentage instead of raw milliseconds)
- Resolved all PHPStan errors

#### Documentation

- Rewrote README with driver-agnostic positioning
- Clarified ecosystem integration with Queue Metrics, Queue Autoscale, and System Metrics

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.1.0...v1.2.0

## v1.1.0 - PHP 8.4 & 8.5 Support - 2026-01-03

### What's Changed

#### Features

- Add PHP 8.4 and 8.5 support

#### Dependencies

- Updated all 58 dependencies to latest versions
- `laravel/framework` v12.39.0 → v12.44.0
- `pestphp/pest` v4.1.4 → v4.3.0
- `phpstan/phpstan` 2.1.32 → 2.1.33
- Symfony components updated to v7.4.x/v8.x

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.0.0...v1.1.0

## v1.0.0 - Initial Release - 2026-01-01

### Queue Monitor for Laravel v1.0.0

First stable release of Queue Monitor for Laravel - a comprehensive job monitoring solution.

#### Features

- **Job Lifecycle Tracking**: Complete monitoring of queued, processing, completed, failed, and timeout states
- **Performance Metrics**: CPU time, memory usage, duration tracking for all jobs
- **Exception Capture**: Full exception details including class, message, and trace
- **Tagging System**: Tag jobs for easy filtering and organization
- **REST API**: Complete API for external integrations and dashboards
- **Health Checks**: System health monitoring with degraded state detection
- **Statistics & Analytics**: Per-queue, per-server, and per-job-class statistics
- **Replay Functionality**: Replay failed jobs with preserved context
- **Batch Operations**: Bulk replay and delete operations
- **Pruning**: Automatic cleanup of old job records
- **Horizon Integration**: Detection and context capture for Horizon workers

#### Requirements

- PHP 8.3+
- Laravel 11.x or 12.x

#### Installation

```bash
composer require cboxdk/laravel-queue-monitor








```
#### Documentation

See the [README](https://github.com/cboxdk/laravel-queue-monitor#readme) for full documentation.

## 1.0.0 - 2025-01-21

### Added

- Initial release
- Individual queue job tracking
- Full payload storage for job replay
- Worker and server identification (Horizon vs queue:work)
- CPU, memory, and file descriptor tracking via queue-metrics integration
- Retry chain tracking with complete history
- Comprehensive REST API for external dashboards
- Facade for programmatic access
- Tag-based job organization and analytics
- Global, per-server, per-queue, and per-job-class statistics
- Queue health monitoring
- Failure pattern analysis
- Job replay functionality with validation
- Job cancellation support
- Automatic pruning of old records
- Artisan commands (stats, replay, prune)
- PHPStan Level 9 compliance
- Pest 4 test suite
- Comprehensive documentation

### Package Features

- **Action Pattern**: All business logic in dedicated Action classes
- **DTO Pattern**: Type-safe data transfer objects throughout
- **Repository Pattern**: Clean data access layer with contracts
- **Event-Driven**: Integration via Laravel and queue-metrics events
- **Extensible**: All components replaceable via config bindings
- **PHP 8.3**: Modern PHP with readonly properties and enums
- **Strict Types**: declare(strict_types=1) on all files

### API Endpoints

- `GET /api/queue-monitor/jobs` - List and filter jobs
- `GET /api/queue-monitor/jobs/{uuid}` - Job details
- `POST /api/queue-monitor/jobs/{uuid}/replay` - Replay job
- `DELETE /api/queue-monitor/jobs/{uuid}` - Delete job
- `GET /api/queue-monitor/jobs/{uuid}/retry-chain` - Retry chain
- `GET /api/queue-monitor/statistics` - Global statistics
- `GET /api/queue-monitor/statistics/servers` - Server statistics
- `GET /api/queue-monitor/statistics/queues` - Queue statistics
- `GET /api/queue-monitor/statistics/queue-health` - Health metrics
- `GET /api/queue-monitor/statistics/tags` - Tag analytics
- `POST /api/queue-monitor/prune` - Prune old records

### Artisan Commands

- `queue-monitor:stats` - Display statistics in terminal
- `queue-monitor:replay {uuid}` - Replay specific job
- `queue-monitor:prune` - Prune old job records

### Dependencies

- PHP ^8.3
- Laravel ^10.0 || ^11.0 || ^12.0
- cboxdk/laravel-queue-metrics ^1.0 (hard dependency)
