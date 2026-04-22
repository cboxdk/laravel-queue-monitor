---
title: "Queue Monitor for Laravel"
description: "Track individual queue jobs with payload storage, replay capability, and comprehensive analytics"
weight: 1
---

# Queue Monitor for Laravel

**Production-ready queue monitoring for Laravel with individual job tracking, payload storage, and job replay.**

## Overview

Queue Monitor for Laravel provides granular visibility into every queue job in your Laravel application. Unlike aggregate metrics packages, it tracks each job individually from dispatch to completion, stores payloads for replay, and offers comprehensive analytics.

## Key Capabilities

### Individual Job Tracking
Monitor every single job with complete lifecycle visibility:
- Job queued → processing → completed/failed
- Retry chain tracking
- Exception capture with stack traces
- Server and worker identification

### Job Replay
Re-dispatch failed jobs from stored payloads:
- Complete payload serialization
- Validation before replay
- Retry chain maintenance
- Perfect for development debugging

### Resource Metrics
Track resource usage per job:
- Memory peak (MB)
- Duration (milliseconds)
- CPU time (via queue-metrics integration)
- File descriptors (future)

### Comprehensive Analytics
Deep insights into queue performance:
- Global statistics (success rate, failure rate)
- Per-server analytics
- Per-queue analytics
- Per-job-class analytics
- Queue health scoring
- Failure pattern analysis
- Tag-based analytics

### REST API
Full-featured API for custom dashboards:
- 14 endpoints covering all functionality
- Advanced filtering (20+ parameters)
- Pagination and sorting
- Rate limiting
- Authentication-ready

## Quick Example

```php
use Cbox\LaravelQueueMonitor\Facades\LaravelQueueMonitor as QueueMonitor;

// Get failed jobs from last 24 hours
$filters = new JobFilterData(
    statuses: [JobStatus::FAILED],
    queuedAfter: Carbon::now()->subHours(24)
);

$failed = QueueMonitor::getJobs($filters);

// Replay each one
foreach ($failed as $job) {
    QueueMonitor::replay($job->uuid);
}

// Check statistics
$stats = QueueMonitor::statistics();
echo "Success Rate: {$stats['success_rate']}%\n";
```

## Documentation Structure

### Getting Started
- [Introduction](getting-started/introduction) - Package overview and key features
- [Installation](getting-started/installation) - Setup in 5 minutes
- [Quick Start](getting-started/quickstart) - Common usage patterns
- [Authentication & Access Control](guides/authentication) - Secure the dashboard and REST API
- [Configuration](guides/configuration) - Customize behavior

### Core Features
- [Facade Usage](guides/facade-usage) - Programmatic API
- [Job Replay](guides/job-replay) - Replay system deep dive
- [Advanced Usage](advanced/advanced-usage) - Custom monitoring and dashboards
- [Events](guides/events) - Event-driven integration

### Reference
- [Queue-Metrics Integration](reference/metrics-integration) - Resource tracking
- [API Reference](reference/api-reference) - Complete REST API docs
- [Testing Guide](reference/testing) - Write tests with Pest 4
- [Architecture](reference/architecture) - Package design patterns

## Architecture

Built on solid patterns for maintainability:

- **Action Pattern** - Business logic in single-responsibility classes
- **DTO Pattern** - Type-safe data transfer objects
- **Repository Pattern** - Clean data access abstraction
- **Event-Driven** - Loosely coupled integration
- **SOLID Principles** - Professional code quality

## Requirements

- PHP 8.3+
- Laravel 10.0+
- cboxdk/laravel-queue-metrics ^1.0

## Features at a Glance

| Feature | Description |
|---------|-------------|
| **Job Tracking** | Every job tracked with unique UUID |
| **Payload Storage** | Full serialization for replay |
| **Worker Detection** | Horizon vs queue:work auto-detection |
| **Retry Chains** | Complete retry attempt history |
| **Resource Metrics** | Memory, CPU, duration per job |
| **Tag System** | Categorize and filter by tags |
| **REST API** | 14 endpoints for dashboards |
| **Job Replay** | Re-dispatch from stored payloads |
| **Analytics** | Global, server, queue, job-class stats |
| **Queue Health** | Real-time health scoring |
| **Facade** | Type-safe programmatic access |
| **Artisan Commands** | CLI tools for monitoring |
| **PHPStan Level 9** | Maximum type safety |
| **Pest 4** | Modern testing framework |

## Next Steps

Start with the [Installation Guide](getting-started/installation) to get up and running in minutes.
