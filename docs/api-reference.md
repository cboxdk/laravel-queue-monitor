# API Reference

The Laravel Queue Monitor package exposes a comprehensive REST API for integrating with external dashboards or monitoring tools.

## Base URL

By default, the API is available at:
`http://your-app.test/api/queue-monitor`

This prefix can be configured in `config/queue-monitor.php`.

## Authentication

**Important:** The API exposes sensitive data (job payloads, stack traces). You **must** secure it in production.

We recommend adding `auth:sanctum` or `auth:api` to the `middleware` configuration in `config/queue-monitor.php`.

## Endpoints

### 1. List Jobs

Retrieve a paginated list of jobs.

**Endpoint:** `GET /jobs`

**Query Parameters:**

| Parameter | Type | Description |
| :--- | :--- | :--- |
| `limit` | int | Number of records per page (default: 20) |
| `offset` | int | Records to skip |
| `statuses[]` | array | Filter by status (e.g. `failed`, `processing`) |
| `queues[]` | array | Filter by queue name |
| `job_classes[]` | array | Filter by job class |
| `tags[]` | array | Filter by tags |
| `search` | string | Search by UUID, job class, or exception message |
| `sort_by` | string | Field to sort by (default: `started_at`) |
| `sort_direction` | string | `asc` or `desc` (default: `desc`) |

**Response:**

```json
{
    "data": [
        {
            "id": 1,
            "uuid": "e0e4...",
            "job_class": "App\\Jobs\\ProcessPayment",
            "status": {
                "value": "failed",
                "label": "Failed",
                "color": "danger"
            },
            "metrics": {
                "duration_ms": 1500,
                "memory_peak_mb": 12.5
            },
            "timestamps": {
                "started_at": "2024-03-20T10:00:00Z"
            }
        }
    ],
    "meta": {
        "total": 50,
        "limit": 20
    }
}
```

### 2. Get Job Details

Retrieve full details for a specific job, including payload and exception trace.

**Endpoint:** `GET /jobs/{uuid}`

**Security Note:** Sensitive keys in the payload (like `password` or `token`) are automatically masked based on the `api.sensitive_keys` configuration.

**Response:**

```json
{
    "data": {
        "uuid": "e0e4...",
        "payload": {
            "user_id": 123,
            "amount": 500,
            "credit_card": "*****" // Redacted
        },
        "exception": {
            "class": "Exception",
            "message": "Payment failed",
            "trace": "..."
        }
    }
}
```

### 3. Replay Job

Re-dispatch a failed job using its original payload.

**Endpoint:** `POST /jobs/{uuid}/replay`

**Response:**

```json
{
    "message": "Job replay initiated",
    "replay_uuid": "new-job-uuid..."
}
```

### 4. Delete Job

Permanently remove a job record.

**Endpoint:** `DELETE /jobs/{uuid}`

### 5. Get Retry Chain

View the history of a job and its retries.

**Endpoint:** `GET /jobs/{uuid}/retry-chain`

**Response:**
Returns a collection of job resources, ordered by attempt number.

### 6. Global Statistics

Get aggregated statistics for the entire system.

**Endpoint:** `GET /statistics`

**Response:**

```json
{
    "data": {
        "total_jobs": 1000,
        "failed_jobs": 50,
        "success_rate": 95.0,
        "avg_duration_ms": 1200,
        "avg_memory_mb": 14.2
    }
}
```

### 7. Queue Health

Get health status for all queues.

**Endpoint:** `GET /statistics/queue-health`

**Response:**

```json
{
    "data": [
        {
            "queue": "default",
            "status": "healthy", // healthy, degraded, failed
            "jobs_per_minute": 120,
            "error_rate": 0.5
        }
    ]
}
```

## Error Handling

The API returns standard HTTP status codes:

*   `200`: Success
*   `404`: Job not found
*   `422`: Validation error (e.g. invalid replay request)
*   `503`: Service unavailable (if health check fails)