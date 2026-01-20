# Changelog

All notable changes to `laravel-queue-monitor` will be documented in this file.

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

### Laravel Queue Monitor v1.0.0

First stable release of Laravel Queue Monitor - a comprehensive job monitoring solution for Laravel.

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
