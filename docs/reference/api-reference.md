---
title: "API Reference"
description: "Complete REST API documentation with all endpoints, parameters, and response examples"
weight: 1
---

# API Reference

Queue Monitor exposes a REST API for integrating with external dashboards or
monitoring tools. This page documents every endpoint the package registers in
`routes/api.php`.

## Base URL

By default, the API is served under:

```
http://your-app.test/api/queue-monitor
```

The prefix is configurable via `api.prefix` in `config/queue-monitor.php`. All
paths below are relative to this prefix.

## Enabling the API

The API is registered only when `api.enabled` is true. It defaults to on in the
`local` environment and off everywhere else. Enable it explicitly in other
environments with `QUEUE_MONITOR_API_ENABLED=true` — but only after adding
authentication (see below).

## Authentication and authorization

The API exposes sensitive data (job payloads, stack traces). Secure it before
enabling it outside local development. Two independent layers apply:

1. **Framework middleware** — add auth middleware to `api.middleware`, e.g.
   `['api', 'auth:sanctum']`. A request that fails it returns `401`.
2. **Authorization gate** — every request passes through the
   `EnsureQueueMonitorEnabled` middleware, which calls your
   `LaravelQueueMonitor::auth(...)` callback. If the callback denies the request,
   the API returns `403`. Outside `local`, access is denied until you register a
   callback.

See [Authentication & Access Control](../guides/authentication) for setup.

## Rate limiting

Every route is wrapped in `throttle:60,1` (60 requests per minute per client) by
default. The limit comes from `api.rate_limit`. Exceeding it returns `429 Too
Many Requests` with a `Retry-After` header.

## Payload redaction

Job payloads and tags returned by the API are redacted before serialization.
Keys listed in `api.sensitive_keys` (default: `password`, `token`, `secret`,
`key`, `authorization`, `api_key`, `credit_card`, `cvv`, `ssn`, `private_key`,
`command`) are masked. Set `api.sensitive_keys` to an empty array to disable
redaction. Raw payloads remain stored unredacted for replay — only responses are
masked.

## Pagination and limits

List endpoints accept a `limit` parameter. The default is `50`
(`api.default_limit`). Any requested `limit` is clamped to `api.max_limit`
(default `1000`); a larger value is silently reduced to the maximum rather than
rejected.

---

## Jobs

### List jobs

Retrieve a filtered, paginated list of jobs.

**Endpoint:** `GET /jobs`

**Query parameters:**

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `limit` | int | Records per page (default: `50`, max: `1000`) |
| `offset` | int | Records to skip (default: `0`) |
| `statuses[]` | array | Filter by status: `queued`, `processing`, `completed`, `failed`, `timeout`, `cancelled` |
| `queues[]` | array | Filter by queue name |
| `connection` | string | Filter by queue connection |
| `job_classes[]` | array | Filter by job class (FQCN) |
| `server_names[]` | array | Filter by server name |
| `worker_id` | string | Filter by worker id |
| `worker_type` | string | Filter by worker type: `queue_work` or `horizon` |
| `tags[]` | array | Filter by tag |
| `queued_after` / `queued_before` | date | Filter by queued timestamp |
| `started_after` / `started_before` | date | Filter by started timestamp |
| `completed_after` / `completed_before` | date | Filter by completed timestamp |
| `min_duration_ms` / `max_duration_ms` | int | Filter by duration |
| `min_attempts` | int | Minimum attempt count |
| `search` | string | Search UUID, job class, or exception message (max 255) |
| `sort_by` | string | One of `queued_at`, `started_at`, `completed_at`, `duration_ms`, `created_at` (default: `queued_at`) |
| `sort_direction` | string | `asc` or `desc` (default: `desc`) |

**Response:**

```json
{
    "data": [
        {
            "id": 1,
            "uuid": "e0e4a3f2-...",
            "job_id": "97",
            "job_class": "App\\Jobs\\ProcessPayment",
            "display_name": "App\\Jobs\\ProcessPayment",
            "connection": "redis",
            "queue": "payments",
            "payload": { "amount": 500, "credit_card": "[REDACTED]" },
            "status": { "value": "failed", "label": "Failed", "color": "danger" },
            "attempt": 2,
            "max_attempts": 3,
            "retried_from_id": null,
            "server_name": "web-01",
            "worker_id": "worker-1",
            "worker_type": { "value": "horizon", "label": "Horizon" },
            "metrics": {
                "cpu_time_ms": 240,
                "memory_peak_mb": 12.5,
                "file_descriptors": null,
                "duration_ms": 1500,
                "duration_seconds": 1.5
            },
            "exception": {
                "class": "RuntimeException",
                "short_class": "RuntimeException",
                "message": "Payment gateway timeout"
            },
            "tags": ["payments", "high-priority"],
            "timestamps": {
                "queued_at": "2026-08-26T10:00:00+00:00",
                "started_at": "2026-08-26T10:00:01+00:00",
                "completed_at": "2026-08-26T10:00:02+00:00",
                "created_at": "2026-08-26T10:00:00+00:00",
                "updated_at": "2026-08-26T10:00:02+00:00"
            },
            "flags": {
                "is_finished": true,
                "is_successful": false,
                "is_failed": true,
                "is_retryable": true,
                "is_retry": false
            }
        }
    ],
    "meta": {
        "total": 128,
        "limit": 50,
        "offset": 0,
        "has_filters": true
    }
}
```

### List failed jobs

**Endpoint:** `GET /jobs/failed`

**Query parameters:** `limit` (default: `100`, clamped to `api.max_limit`).

**Response:** a job collection (`{ "data": [ ... ], "meta": { "total": N } }`)
of failed jobs, each shaped like the job resource above.

### List recent jobs

**Endpoint:** `GET /jobs/recent`

**Query parameters:** `limit` (default: `100`, clamped to `api.max_limit`).

**Response:** a job collection of the most recently queued jobs.

### Get job details

**Endpoint:** `GET /jobs/{uuid}`

Returns the full job resource (same shape as an item in the list response) with
the redacted payload and, when failed, the exception summary. Returns `404` if
the UUID is unknown.

```json
{
    "data": {
        "uuid": "e0e4a3f2-...",
        "job_class": "App\\Jobs\\ProcessPayment",
        "payload": { "amount": 500, "credit_card": "[REDACTED]" },
        "exception": {
            "class": "RuntimeException",
            "short_class": "RuntimeException",
            "message": "Payment gateway timeout"
        }
    }
}
```

### Delete a job

**Endpoint:** `DELETE /jobs/{uuid}`

Permanently removes a single job record. Returns `404` if the UUID is unknown.

```json
{ "message": "Job deleted successfully" }
```

### Get retry chain

**Endpoint:** `GET /jobs/{uuid}/retry-chain`

Returns the job and every job in its retry chain as a collection, ordered by
attempt.

```json
{ "data": [ /* job resources */ ], "meta": { "total": 3 } }
```

### Replay a job

**Endpoint:** `POST /jobs/{uuid}/replay`

Re-dispatches a job from its stored payload. Requires payload storage to have
been enabled when the job ran.

**Success (200):**

```json
{
    "message": "Job replayed successfully",
    "data": {
        "original_uuid": "e0e4a3f2-...",
        "new_uuid": "b1c2d3e4-...",
        "new_job_id": "412",
        "queue": "payments",
        "connection": "redis",
        "replayed_at": "2026-08-26T10:05:00+00:00"
    }
}
```

**Failure (422)** — e.g. no stored payload:

```json
{ "message": "Failed to replay job", "error": "No payload stored for this job" }
```

---

## Statistics

All statistics endpoints wrap their result in a `data` envelope.

### Global statistics

**Endpoint:** `GET /statistics`

```json
{
    "data": {
        "total": 10000,
        "completed": 9600,
        "failed": 350,
        "timeout": 50,
        "processing": 12,
        "queue_backlog": 38,
        "success_rate": 96.48,
        "failure_rate": 3.52,
        "avg_duration_ms": 842.11,
        "max_duration_ms": 58210,
        "avg_memory_mb": 24.6,
        "max_memory_mb": 187.4
    }
}
```

Aggregates are limited to the window in `metrics_window_hours` (default 24h).

### Per-server statistics

**Endpoint:** `GET /statistics/servers`

**Query parameters:** `server` (optional) — limit to one server.

```json
{
    "data": [
        {
            "server_name": "web-01",
            "total_jobs": 5000,
            "completed": 4820,
            "failed": 180,
            "success_rate": 96.4,
            "avg_duration_ms": 780.5
        }
    ]
}
```

### Per-queue statistics

**Endpoint:** `GET /statistics/queues`

**Query parameters:** `queue` (optional) — limit to one queue.

```json
{
    "data": [
        {
            "queue": "payments",
            "connection": "redis",
            "total_jobs": 3200,
            "completed": 3100,
            "failed": 100,
            "processing": 4,
            "success_rate": 96.88,
            "avg_duration_ms": 910.2
        }
    ]
}
```

### Per-job-class statistics

**Endpoint:** `GET /statistics/job-classes`

**Query parameters:** `job_class` (optional) — limit to one class.

```json
{
    "data": [
        {
            "job_class": "App\\Jobs\\ProcessPayment",
            "total_jobs": 3200,
            "completed": 3100,
            "failed": 100,
            "success_rate": 96.88,
            "avg_duration_ms": 910.2,
            "max_duration_ms": 58210
        }
    ]
}
```

### Queue health

**Endpoint:** `GET /statistics/queue-health`

Per-queue health scored over the last hour.

```json
{
    "data": [
        {
            "queue": "payments",
            "total_last_hour": 420,
            "processing": 4,
            "failed": 6,
            "avg_duration_ms": 910.2,
            "health_score": 98.57,
            "status": "healthy"
        }
    ]
}
```

`status` is `healthy` (score ≥ 95), `degraded` (≥ 75), or `unhealthy`.

### Failure patterns

**Endpoint:** `GET /statistics/failure-patterns`

The top exception classes (up to 10) and how many job classes each affects.

```json
{
    "data": {
        "top_exceptions": [
            {
                "exception_class": "RuntimeException",
                "count": 240,
                "affected_job_classes": 6
            }
        ]
    }
}
```

### Tag analytics

**Endpoint:** `GET /statistics/tags`

```json
{
    "data": [
        {
            "tag": "high-priority",
            "count": 1800,
            "successful_count": 1750,
            "success_rate": 97.22
        }
    ]
}
```

---

## Maintenance

### Prune jobs

**Endpoint:** `POST /prune`

Deletes old job records. With no body, the configured retention settings apply.

**Body parameters:**

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `days` | int | Retain records newer than this many days (min 1) |
| `max_rows` | int | Keep at most this many rows, deleting oldest first (min 1) |
| `statuses[]` | array | Only prune these statuses (validated against the status enum) |

```json
{ "message": "Jobs pruned successfully", "deleted": 1240 }
```

---

## Batch operations

Both batch endpoints accept either an explicit `uuids[]` list or a `filters`
object. `filters` uses the same keys as the [job filter](#list-jobs)
(`statuses`, `queues`, `job_classes`, `server_names`, `search`, `queued_after`,
`queued_before`, `min_attempts`, `min_duration_ms`). Unknown filter values are
rejected with `422` rather than silently dropped, so a batch never widens to
match every job. `max_jobs` is capped at `batch.max_jobs` (default `1000`).

### Batch replay

**Endpoint:** `POST /batch/replay`

```json
{
    "message": "Batch replay completed",
    "success": 42,
    "failed": 3,
    "errors": ["b1c2...: No payload stored"]
}
```

### Batch delete

**Endpoint:** `POST /batch/delete`

```json
{ "message": "Batch delete completed", "deleted": 45, "failed": 0 }
```

---

## Stuck jobs

A stuck job is one processing longer than `health.stuck_job_minutes`
(default 30).

### Resolve specific stuck jobs

**Endpoint:** `POST /stuck-jobs/resolve`

**Body parameters:**

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `action` | string | Required: `delete` or `retry` |
| `uuids[]` | array | Required, at least one UUID |

```json
{ "message": "3 stuck job(s) resolved, 3 retried", "resolved": 3, "replayed": 3, "errors": [] }
```

### Resolve all stuck jobs

**Endpoint:** `POST /stuck-jobs/resolve-all`

**Body parameters:** `action` — `delete` or `retry`. Operates on every currently
stuck job (up to `batch.max_jobs`).

```json
{ "message": "No stuck jobs found", "resolved": 0, "replayed": 0, "errors": [] }
```

---

## Health and monitoring

### System health

**Endpoint:** `GET /health`

Runs the database, recent-activity, stuck-job, error-rate, queue-backlog, and
storage checks. Returns HTTP `200` when the overall status is `healthy`, and
`503` when it is `degraded`.

```json
{
    "status": "healthy",
    "checks": {
        "database": { "healthy": true, "message": "Database reachable" },
        "stuck_jobs": {
            "healthy": true,
            "message": "No stuck jobs detected",
            "details": { "stuck_count": 0, "threshold_minutes": 30, "stuck_jobs": [] }
        }
    },
    "timestamp": "2026-08-26T10:00:00+00:00"
}
```

### Health score

**Endpoint:** `GET /health/score`

```json
{ "score": 92, "status": "healthy" }
```

`status` is `healthy` (score ≥ 80), `degraded` (≥ 60), or `unhealthy`.

### Active alerts

**Endpoint:** `GET /health/alerts`

```json
{
    "alerts": {
        "stuck_jobs": { "severity": "warning", "message": "5 jobs stuck in processing for > 30 minutes", "count": 5 }
    },
    "has_critical": false,
    "count": 1
}
```

---

## Export

Export endpoints are bounded by `export.default_limit` (default `1000`) and
`export.max_rows` (default `5000`). The CSV/JSON exports accept the same filter
parameters as [`GET /jobs`](#list-jobs).

### CSV export

**Endpoint:** `GET /export/csv`

Returns `text/csv` as a file download
(`Content-Disposition: attachment; filename="queue-monitor-jobs-YYYY-MM-DD.csv"`).

### JSON export

**Endpoint:** `GET /export/json`

```json
{
    "data": [ /* flattened job rows */ ],
    "meta": { "count": 1000, "exported_at": "2026-08-26T10:00:00+00:00" }
}
```

### Statistics report

**Endpoint:** `GET /export/statistics`

```json
{
    "generated_at": "2026-08-26T10:00:00+00:00",
    "global": { /* global statistics */ },
    "servers": [ /* per-server statistics */ ],
    "queue_health": [ /* per-queue health */ ]
}
```

### Failed-jobs report

**Endpoint:** `GET /export/failed-jobs`

```json
{
    "generated_at": "2026-08-26T10:00:00+00:00",
    "total_failed": 350,
    "by_exception": { "RuntimeException": { "count": 240, "jobs": ["e0e4..."] } },
    "by_queue": { "payments": 100 },
    "recent_failures": [
        {
            "uuid": "e0e4...",
            "job_class": "App\\Jobs\\ProcessPayment",
            "exception": "RuntimeException",
            "failed_at": "2026-08-26T09:59:00+00:00"
        }
    ]
}
```

---

## Dashboard data endpoints

The built-in web dashboard is backed by its own JSON endpoints under the UI
route prefix (`queue-monitor` by default), not the API prefix. They are gated by
the same access-control callback but the `web` middleware group. The
throughput/timeline endpoints accept a `minutes` parameter restricted to a fixed
whitelist — `15`, `60`, `360`, or `1440`. Any other value falls back to `60`.

| Endpoint | `minutes` | Notes |
| :--- | :--- | :--- |
| `GET /queue-monitor/metrics` | 15 / 60 / 360 / 1440 | Overview: stats, queues, alerts, recent jobs, throughput chart. Recent-jobs count follows `ui.per_page`. |
| `GET /queue-monitor/autoscale/timeline?queue={name}` | 15 / 60 / 360 / 1440 | Per-queue scaling timeline (job volume, duration, memory, worker decisions). |

---

## Errors

The API uses standard HTTP status codes:

| Code | Meaning |
| :--- | :--- |
| `200` | Success |
| `401` | Unauthenticated — the framework auth middleware you added (e.g. `auth:sanctum`) rejected the request |
| `403` | Forbidden — the `LaravelQueueMonitor::auth(...)` gate denied the request |
| `404` | Job not found |
| `422` | Validation error (invalid filter, replay with no payload, etc.) |
| `429` | Too many requests — the `throttle:60,1` rate limit was exceeded |
| `503` | Queue Monitor or its API is disabled, or `GET /health` reports `degraded` |
