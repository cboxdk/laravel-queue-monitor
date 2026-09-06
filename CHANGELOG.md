# Changelog

All notable changes to `laravel-queue-monitor` will be documented in this file.

## Unreleased

### Fixes

- **Batched jobs are recorded under the queue they were actually pushed to, not `default`.** `RecordJobQueuedAction` read the queue from the job *instance*. For jobs dispatched inside a `Bus::batch(...)->onQueue(...)`, Laravel applies the queue at the bulk-push level, so the instance's `$queue` stays `null` and every batched job was recorded/displayed under the literal `default`. The `JobQueued` event already carries the real destination in `$event->queue`; it is now preferred, falling back to the instance and then to the connection's configured default queue name. (Mirrors the fix `cboxdk/laravel-queue-metrics` shipped for its `JobQueuedListener` in v3.3.0.)

## v1.10.2 — Overview declutter - 2026-08-27

### Fixes

- **Declutter the overview.** Each metric tile stacked three decorations around one number — a colored status pill in the head (several, like `Avg` and `Info`, carrying no data), a delta word, and a caption — and the sidebar footer held a "Refresh contract" box written as internal engineering notes rather than operator copy. Every tile is now the label, the value, and a single supporting line that keeps the one status word that actually signals health (Watch / Stable / On target / Ready) with concise context; the redundant head pills and the sidebar box (and its now-dead CSS) are gone.

## v1.10.1 — Deploy-safe statistics cache - 2026-08-27

### Fixes

- **Survive a deploy: the dashboard no longer 500s on cached value objects from a previous release.** The typed read models introduced in v1.10.0 cache value objects, which serialize as objects. A persistent cache (redis or database) outlives a deploy, so a `GlobalStatistics` — or any cached value object — serialized by the previous release unserialized to `__PHP_Incomplete_Class` in the new one, failing its strict return type with a `TypeError` and returning 500 on every `metrics`/`health` poll. The cache key now includes the installed package release, so a deploy orphans every pre-deploy entry, and every cache read treats an incomplete class as a miss and recomputes — both deploy- and store-agnostic, so it self-heals even where `cache:clear` did not reach the store the dashboard uses.

## v1.10.0 — Contracts-first and typed read models - 2026-08-26

An internal architecture pass with no change to any JSON response, dashboard, CLI, or configuration behaviour — every existing test passes unchanged, which is the guarantee that output is byte-for-key identical.

### Maintenance

- **Contracts-first throughout.** The six service classes (alerting, health, infrastructure, export, dashboard cache, worker context) now sit behind interfaces bound via a `services` config map, mirroring the repository binding. A host overrides or fakes any of them by rebinding its contract — the same replacement model the repositories already used, now uniform across the package. An arch test enforces final-by-default on the domain namespaces, so behaviour changes by rebinding a contract, never by subclassing an implementation. (#40, #41)
- **The whole read path flows typed.** The statistics repository and the four read services return 24 `final readonly` value objects instead of `array<string,mixed>` bags. Each implements `Arrayable` + `JsonSerializable` + `ArrayAccess`, so responses, the dashboard, the terminal UI, and `--json` output are unchanged; arrays remain only at true serialization edges (external Horizon/autoscale telemetry with runtime-variable shapes). (#43, #44)
- Single-sourced the health-score formula (it lived in two places, one without a div-by-zero guard) and the alerting thresholds (they hardcoded values the health checks read from `queue-monitor.health.*`, so raising a threshold made the two disagree); added `error_rate_critical_threshold` for the alert escalation. (#40, #42)
- Fixed a contract-downcast that would fatal the drill-down endpoint for any host binding a custom statistics repository, promoted three service-location call sites to constructor injection, and removed dead code (an unused form request and two command options that were never read). (#40)
- Set the test suite memory limit to 512M in the composer script and CI, matching the sibling packages — the suite had grown to sit right at PHP's default 128MB CLI limit. (#43)

### Note for contract implementers

If you implement `StatisticsRepositoryContract` or one of the new `Services\Contracts\*` interfaces directly, the read methods now declare value-object return types instead of `array`. Update your implementations' return types accordingly (or return the shipped value objects). Consumers of these methods need no change — the value objects array-access and serialize exactly like the arrays they replaced.

## v1.9.0 — Scaling timeline and a hardening pass - 2026-08-26

### Features

- **Per-queue scaling timeline on the autoscale tab.** The tab now opens with the queue's load and the autoscaler's worker decisions on one shared time axis: per-minute job volume, duration (average and max), and peak memory drawn from the recorded jobs, next to a step chart of the actual worker count against the autoscaler's target. Queue chips and a 15m/1h/6h/24h range selector pick the view; a live strip shows the queue's processing/waiting/delayed counts, the worker range observed in the window, and the worker memory limit. It reads the data the package already records — no new instrumentation — and the worker panel appears when scaling events exist. (#35)
- **Opt out of automatically running the package migrations.** Hosts that publish the migrations and manage them under their own database user can set `QUEUE_MONITOR_ENABLE_MIGRATIONS=false` (config `enable_migrations`); publishing via `vendor:publish` keeps working with the flag disabled. (#28, thanks @jackbayliss)

### Fixes

- **The dashboard refreshed even when auto-refresh was off.** Three causes compounded: the autoscale tab's toggle was inverted (with it off, the global loop still refreshed the tab; with it on, a second timer took over — so "off" refreshed *faster* than "on"), `isLive` gated only the interval and not the popstate, pending-refresh, and retry paths, and Alpine's auto-invoked `init()` ran a second time because of a stray `x-init`, doubling requests and listeners on load. Live/Paused is now the single authoritative gate, persisted across reloads and reflected in the chip and pause button; polling stops in hidden tabs and resumes on return. (#31)
- **The storage health check reported "not available" for hosts using a Laravel connection-level table prefix.** Its raw `information_schema` lookup didn't apply the connection prefix that the query builders add automatically. The physical table name now combines both prefixes, and `table_prefix` is overridable via `QUEUE_MONITOR_TABLE_PREFIX`. (#32)
- **Batch and prune filters failed open on a bad status.** An unrecognised status in a batch delete/replay or prune filter was silently dropped, widening a scoped delete to every matching job; invalid statuses are now rejected with a 422. (#36)
- **The drill-down job summary leaked redacted payload values.** It was parsed from the raw serialized command, which the payload redactor never sees, so a property named e.g. `password` showed its value even though every other surface masks it; the summary now applies the `api.sensitive_keys` policy. Dashboard job-detail tags are redacted too, and the queued-job capture path restricts `unserialize()` to the declared command class. (#36)
- **Timeout recording could mark the wrong retry attempt,** and resolving a stuck job wrote a non-existent `finished_at` column that was silently dropped. Timeout now targets the latest attempt like completed/failed recording, and stuck-job resolution records `completed_at` and a duration. `queue-monitor:health --json` now carries the failing exit code on the readiness and health paths — the exact path a CI or deploy gate scripts. (#36)
- **Statistics caching was cold under load.** A busy queue bumped the cache version on every job lifecycle write, so it invalidated faster than any entry could be reused. The bump is now throttled (configurable via `cache.bust_throttle_seconds`); the first write after a quiet period still invalidates immediately. Per-queue SLA compliance is aggregated in SQL instead of hydrating every last-hour job row into PHP. (#36)
- **Eight high-severity dashboard UX issues:** failures now show a dismissible error banner instead of serving stale data silently; the mislabelled "Ignore" control that deleted a job is now "Delete"; the time-range control is wired to the chart; the attention list is genuinely selectable (it tracked whichever job sorted first, so actions could hit a different job every few seconds); batch actions only target visible rows; the fabricated "default" queue empty state and the dead Environment control are gone. (#33)
- **Consistency and accessibility pass:** one time/duration/percent formatting story across every tab, a fully keyboard-operable jobs table with `aria-sort` and focus-visible outlines, a unified confirm dialog (role, Escape, focus management) replacing the native `confirm()` calls, corrected Back-button behaviour, and `prefers-reduced-motion` support. (#34)
- **Dashboard history and dialog follow-ups:** Back from a freshly loaded dashboard restores the right tab and resets pagination/sorting; action errors survive a background refresh instead of being wiped a moment later; the confirm dialog dismisses on a backdrop click; the document title reflects the active view on load. (#37)
- **Keep the sidebar column dark for the full page height without losing the sticky nav.** The dark background moves to the grid column so it follows tall content, while the sidebar stays sticky at viewport height and the menu scrolls internally when the viewport is shorter than the nav. (#27, thanks @jackbayliss)

### Documentation

- Rewrote the REST API reference to cover all 26 endpoints (it documented seven), with correct defaults, error codes, and payload-redaction notes; corrected the retention defaults that contradicted the shipped config (`days` 7, not 30; `['completed','failed','timeout']`, not `['completed']`); documented the new configuration keys; moved the docs into the standard topic-folder layout with a generated `requirements.md`; and reframed the Horizon mention as an optional integration. (#32, #38)

### Maintenance

- Added a supply-chain gate: a dependency license check (`composer license-check`), a deterministic CycloneDX SBOM published as a CI artifact, a `SECURITY.md` with GitHub private vulnerability reporting, and a `composer qa` aggregate. CI now gates `pint --test`, the license check, and `composer audit` on every pull request, adds a lowest-dependencies test leg, and drops the redundant redis service and the auto-fixer that pushed unreviewed style commits. (#39)

## v1.8.0 — Failure fuse visibility - 2026-08-25

### Features

- Record the autoscaler's failure-fuse events (`FuseTripped`, `FuseProbing`, `FuseRecovered`) as scaling events. A fuse trip previously reached the dashboard as an unexplained scale-down: the queue is deep, the workers are going away, and nothing on screen says why. The timeline now names the cause, the failure rate that caused it, and the probe that recovers it.
- Show which queues the fuse is still holding, on both the web and terminal dashboards. The timeline records the moment a fuse tripped; a fuse that tripped an hour ago has scrolled off it while the queue it holds is still at zero workers. `scaling.open_fuses` reports the current state per queue, and `summary.fuse_trips` counts trips in the last hour without counting them as scaling decisions.
- Flag unstable cluster leadership. Taking the autoscaler's lease discards worker placement, the anti-flapping window and the fair-share ledger's position, so leadership that keeps moving means none of them ever completes. Repeated changes inside `queue-monitor.cluster.leadership_window_seconds` now record a `leadership_unstable` cluster event — once per window, so an unstable cluster does not bury its own timeline — and the cluster panel says what the churn is costing.

### Fixes

- Stop `vendor:publish` in the test suite from leaving a published copy of every Blade view inside the testbench app, where it shadowed the package's own views for every later render test — including subsequent runs, because `vendor:publish` will not overwrite an existing file without `--force`.
- Raise the amber text on the autoscale panels to at least 4.5:1 against its background (4.7:1 measured), and the cluster host figure past the 3:1 large-text floor it sat just under.

### Maintenance

- Add a scheduled `composer audit` workflow. The package ships no `composer.lock`, so a fresh resolve was always clean while existing checkouts drifted — fourteen advisories in `guzzlehttp/guzzle`, `guzzlehttp/psr7` and `league/commonmark` had accumulated unnoticed against dependencies this repository never touched. All fourteen clear on a plain `composer update`; the workflow now catches the next one.

## v1.7.4 — Database queue driver fix and launch hardening - 2026-07-08

### Fixes

- **Support integer job ids from the `database` queue driver.** Laravel's `DatabaseJob::getJobId()` returns the integer auto-increment id even though the `Job` contract declares `getJobId(): string`, which threw a `TypeError` on every processed job under `QUEUE_CONNECTION=database`. Job ids are now normalized to string when recording processing and at the repository boundary (`findByJobId` / `findLatestAttemptByJobId`).
- Portable dashboard analytics SQL across MySQL/MariaDB, PostgreSQL, SQL Server, and SQLite.
- Respect the configured `queue-monitor.database.connection` in dashboard queries.
- Respect `shouldBeMonitored()` / `shouldStorePayload()`, enforce `payload_max_size`, and keep internal deferred tag jobs out of monitoring.
- Normalized tag records for tag filtering instead of driver-specific JSON queries.
- Ship precompiled package-local dashboard assets instead of loading from public CDNs; add `inline`/`public`/`none` asset modes.
- Environment-aware defaults for payload storage and REST API route registration (enabled in `local`, off elsewhere unless explicitly set).
- Add `queue-monitor:health --readiness` launch checks with configurable thresholds.
- Mask serialized command payloads in API/dashboard responses by default.
- Configurable API, export, and batch-operation limits.

See the [CHANGELOG](CHANGELOG.md) for the full list.

## v1.7.3 — Persist autoscale scaling decisions - 2026-05-19

### Fixes

- **`handleScalingDecision` listener no longer drops every event** — The listener guarded `$decision->action()` with `property_exists($decision, 'action')`, but `action()` is a method on `Cbox\LaravelQueueAutoscale\Scaling\ScalingDecision`, not a property. `property_exists()` returns `false` for methods, so the ternary fell through to `null`. Every insert then failed with `SQLSTATE[23000]: Column 'action' cannot be null` (NOT NULL constraint) — exceptions were caught and `report()`'d but no row persisted. Net effect: `queue_monitor_scaling_events` only ever contained rows from `handleWorkersScaled` (`up`/`down`), so the `/queue-monitor/autoscale` summary stat cards (`Total Decisions`, `Scale Ups`, `Scale Downs`, etc., which filter on `scale_up`/`scale_down`/`hold`) showed `0` regardless of cluster activity. The guard is now `method_exists()`, matching what's actually being checked.
- **Same fix for `isSlaBreachRisk`** — same `property_exists` vs. method mismatch on the SLA breach risk flag. Less visible because the fallback was `false` (a valid value), but the field was always `false` in persisted rows when the call should have returned otherwise.

### Testing

- 445 tests, 1216 assertions
- New regression test in `ListenerTest`: `handleScalingDecision` with a duck-typed `ScalingDecision` (readonly props + `action()` + `isSlaBreachRisk()` methods) now writes a row with the correct action string. The existing "tolerates malformed event" test is retained.

**Full Changelog**: https://github.com/cboxdk/laravel-queue-monitor/compare/v1.7.2...v1.7.3

## v1.7.2 - 2026-04-30

### Fixes

- **Autoscale stat cards wrong total**: `Total Decisions` now only counts actionable events (scale_up, scale_down, sla_breach, sla_recovered, sla_breach_predicted) instead of all events including hold/null decisions.
- **Autoscale tab not auto-refreshing**: Include the autoscale tab in the main auto-refresh loop so stat cards update automatically.

## v1.7.1 - 2026-04-30

### Fixes

- **Success rate calculation**: Only count finished jobs (completed + failed/timeout) in success rate, excluding queued/processing jobs that incorrectly deflated the metric.
- **Drill-down chart redrawing**: Stop the throughput chart from constantly disposing and recreating on every auto-refresh. Chart now updates data in-place via `setOption()`.
- **Drill-down recent jobs empty**: Convert `recent_jobs` from Collection to plain array (`->values()->all()`) to prevent cache serialization issues on direct page load.
- **Chart rendering on direct navigation**: Retry chart initialization via `requestAnimationFrame` when the DOM element has zero width during initial layout, fixing missing throughput charts on `/queue-monitor/queue/{name}`.
- **Infrastructure TypeError**: Guard all `infrastructure.workers.workload` and `infrastructure.workers.supervisors` accesses with optional chaining to prevent `Cannot read properties of undefined` errors.
- **Init race condition**: Restore URL filters before navigation so only one fetch fires with correct filters instead of two competing fetches.

### Changes

- Add Refresh buttons to Overview, Jobs, and Drill-down views.
- Expand auto-refresh to cover drill-down views, Health tab, and Jobs tab with active filters.
- Always re-fetch data on tab switch for fresh state.

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
