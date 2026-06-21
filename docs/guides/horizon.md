---
title: "Laravel Horizon Integration"
description: "How Queue Monitor works with Laravel Horizon and where the responsibilities differ"
weight: 16
---

# Laravel Horizon Integration

Queue Monitor does not require Horizon. It tracks jobs through Laravel queue events and works with `database`, `redis`, `sqs`, `beanstalkd`, and custom drivers that fire those events.

When Horizon is installed, Queue Monitor can enrich the dashboard with supervisor, workload, jobs-per-minute, and worker process data from Horizon's repositories.

## Responsibility Split

| Area | Horizon | Queue Monitor |
|------|---------|---------------|
| Worker process management | Yes | No |
| Redis queue dashboard | Yes | Optional enrichment only |
| Per-job payload storage | No | Yes |
| Replay from stored payload | No | Yes |
| Driver-agnostic job tracking | No, Redis queue only | Yes |
| Exception and retry chain analytics | Partial | Yes |

## Production Requirements

If you use Horizon, follow Horizon's own production requirements:

- Use Redis as the queue connection for Horizon-managed workers.
- Run Horizon under a process monitor such as Supervisor.
- Protect Horizon's `/horizon` dashboard with its `viewHorizon` gate.
- Keep Horizon's worker timeout greater than any job-level timeout and lower than the queue connection's `retry_after`.

Queue Monitor does not replace those requirements. It observes jobs and reads Horizon metadata when available.

## Detection Semantics

Queue Monitor distinguishes between:

- Horizon package installed
- Horizon repositories reachable
- Horizon supervisors/workloads available
- Jobs actually processed by Horizon workers

If Horizon is installed but Redis, bindings, or supervisor state are unavailable, Queue Monitor should degrade gracefully and continue showing normal queue job data.

## Tags

Queue Monitor records tags from a job's `tags()` method. It does not currently reproduce every Horizon automatic tagging behavior. If tags are important for filtering, define them explicitly on the job:

```php
public function tags(): array
{
    return ['customer:'.$this->customerId, 'emails'];
}
```

## Troubleshooting

If Horizon jobs appear as `queue_work`, verify:

```bash
php artisan horizon:status
php artisan queue:failed
```

Then check that Horizon detection is enabled:

```php
'worker_detection' => [
    'horizon_detection' => true,
],
```
