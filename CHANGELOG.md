# Changelog

All notable changes to `laravel-queue-monitor` will be documented in this file.

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
- gophpeek/laravel-queue-metrics ^1.0 (hard dependency)
