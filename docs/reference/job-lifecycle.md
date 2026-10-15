---
title: "Job Lifecycle and Attempts"
description: "How each job attempt becomes a row, what every status means, and how late outcomes of redelivered jobs are matched"
weight: 3
---

# Job Lifecycle and Attempts

Queue Monitor records **one row per attempt**. A job that runs once has one row; a
job that is retried, or redelivered by the broker, has one row per attempt. All rows of
one job share a `job_id` and are linked through `retried_from_id`, and together they
form the job's retry chain (`QueueMonitor::getRetryChain($uuid)`).

This page explains how rows are created and closed, what each status means, and how
Queue Monitor keeps every outcome on the row of the attempt that produced it, even
when two attempts of the same job run at the same time.

## Identifying a Job

| Column | Meaning |
|---|---|
| `uuid` | Identifies this row, so one attempt. Each attempt has its own uuid. |
| `job_id` | The job key, shared by every attempt of the job. It is the driver's job id (Redis payload id, SQS message id, database row id). Sync jobs have no driver id, so their key is the payload uuid. |
| `attempt` | The attempt number the broker reported for this run. |
| `retried_from_id` | The `id` of the previous attempt's row, or `null` for the first attempt. |
| `job_class` | The fully qualified class name of the queued command. |
| `display_name` | What the command's `displayName()` reports, used for display. |

### `job_class` and `display_name`

`job_class` is always the class name of the command that was queued. `display_name`
is a label, and is often the same string. Group, filter and alert on `job_class`, and
show `display_name` to people.

| What was queued | `job_class` | `display_name` |
|---|---|---|
| A job class | `App\Jobs\SendInvoice` | `App\Jobs\SendInvoice`, or what its `displayName()` returns |
| A queued closure | `Illuminate\Queue\CallQueuedClosure` | `Closure (web.php:42)` |
| A queued mailable | `Illuminate\Mail\SendQueuedMailable` | The mailable class |
| A queued notification | `Illuminate\Notifications\SendQueuedNotifications` | The notification class |
| A queued event listener | `Illuminate\Events\CallQueuedListener` | The listener class |

Closures, mailables, notifications and listeners are queued inside a framework wrapper,
and that wrapper is the class the worker runs. To find every run of one mailable or
listener, filter on `display_name`.

Two cases need care:

- **Sync jobs.** These are `dispatch_sync()` calls and jobs on the `sync` connection.
  Laravel runs them inline without raising `JobQueued`, so their row is created when
  they start processing, from the queue payload. The class is taken from the payload's
  `data.commandName`. Earlier versions took the payload's display name as `job_class`
  for these rows, so a job with a custom `displayName()` was recorded under a class
  name that does not exist. Rows recorded before the upgrade keep that value.
- **Jobs without a `displayName()` method.** For a row recorded when the job was
  queued, `display_name` is `null`. For a row first recorded when the job started
  processing (sync jobs, for example), `display_name` is the class name, because
  Laravel writes that into the payload.

## Statuses

| Status | Set when | Final? |
|---|---|---|
| `queued` | The job was pushed (`JobQueued`), or an attempt released the job back onto the queue | No |
| `processing` | A worker started the attempt (`JobProcessing`) | No |
| `completed` | The attempt finished without error (`JobProcessed`) | Yes |
| `failed` | The attempt threw. This is recorded on `JobFailed` for the final attempt, and on `JobExceptionOccurred` for an attempt with retries left. | Yes |
| `timeout` | Either the worker's own timeout fired (`JobTimedOut`), or Queue Monitor concluded the attempt will never report back. See [Inferred Timeouts](#inferred-timeouts). | Yes, but a late real outcome replaces an inferred timeout |
| `cancelled` | The job was cancelled through Queue Monitor | Yes |
| `debounced` | The job was superseded by a newer debounced dispatch | Yes |

## How Each Queue Event Is Recorded

| Event | What happens |
|---|---|
| `JobQueued` | A row is created with status `queued` and attempt `1`. |
| `JobProcessing`, first attempt | The queued row becomes `processing`. If there is no row, because the job is a sync job or monitoring was enabled mid-flight, one is created, using the attempt number the broker reports. |
| `JobProcessing`, later attempt | If the previous row is `queued` (it was released), that row is reused and its attempt number is updated. If not, the previous row is closed and a new row is created for this attempt, linked through `retried_from_id`. |
| `JobExceptionOccurred` | The exception is recorded on the row of the attempt that threw, and the row is closed as `failed` with the attempt's duration. On the final attempt `JobFailed` has already recorded it, and the row is left as it is. |
| `JobProcessed` | The attempt's row becomes `completed` with its duration. A released attempt is parked as `queued` instead. |
| `JobFailed` | The attempt's row becomes `failed` with the exception and its duration. |
| `JobTimedOut` | The attempt's row becomes `timeout` with its duration and a `JobTimeout` exception. |

### Released Jobs

A job released back onto the queue fires `JobProcessed`, but it has not finished. A
release comes from rate limiting, job middleware or `$this->release()`. Its row is
parked as `queued`, and the next delivery reuses the same row and updates its attempt
number. A release therefore does not leave a duplicate row or a false failure.

### Intermediate Failures

Laravel only fires `JobFailed` for the final attempt. When an earlier attempt throws,
the worker raises `JobExceptionOccurred` and then releases the job for a retry. Queue
Monitor closes the attempt's row as `failed` on `JobExceptionOccurred`, with the
exception and the duration of the attempt itself. The backoff and the wait until the
next delivery are not part of it. The next attempt gets a row of its own, linked
through `retried_from_id`.

For the final attempt the worker raises `JobFailed` first, which records the failure,
and `JobExceptionOccurred` after it. The second event leaves the row alone, so the
exception and duration are recorded once.

Rows recorded by versions before 1.12.1 left an intermediate failure `processing`
until the next attempt started. Such a row is still closed as `failed` when the next
attempt starts, with no duration, because the time until then includes the backoff.

## Inferred Timeouts

Some attempts never report back. A worker can be killed by `SIGKILL`, the OOM killer, or
a supervisor or container timeout. Its attempt then raises no `JobProcessed`,
`JobFailed` or `JobTimedOut` event, and its row stays `processing`. Once the broker's
redelivery window passes, the message is delivered again:

| Driver | Redelivery window |
|---|---|
| SQS | The queue's visibility timeout |
| Redis | `retry_after` on the queue connection |
| Database | `retry_after` on the queue connection |
| Beanstalkd | The job's time-to-run (`retry_after`) |

When the redelivered attempt starts and finds the previous attempt still `processing`
with no exception, it closes that attempt as **`timeout`** with no duration and opens a
row of its own. The time until redelivery includes the broker's wait, so recording it
as a duration would inflate duration statistics.

This timeout is an **inference**. It means "a newer attempt started while this one had
not reported back". That is not proof that the worker died.

### Slow Jobs That Outlive the Redelivery Window

The same signal appears when a job is merely **slow**. If it runs longer than the
visibility timeout or `retry_after`, the broker hands it to a second worker while the
first is still running it. Both attempts are then in flight at once:

```text
t=0s     worker A starts attempt 1           row 1: processing
t=90s    retry_after lapses; worker B starts attempt 2
                                              row 1: timeout (inferred)
                                              row 2: processing
t=150s   worker A finishes attempt 1         row 1: completed, duration 150 s
t=170s   worker B finishes attempt 2         row 2: completed, duration 80 s
```

When the slow attempt finally reports, **its real outcome replaces the inferred
timeout**:

- `JobProcessed` marks it `completed` with its real duration, measured from its own start.
- `JobFailed` marks it `failed` with the exception and real duration.
- `JobExceptionOccurred`, for an attempt with retries left, marks it `failed` with the
  exception and real duration. Nothing else would close that row again.
- `JobTimedOut` marks it `timeout` with its real duration and a `JobTimeout` exception.
- Per-job CPU and memory from laravel-queue-metrics land on the slow attempt's row.

The newer attempt's row is not touched. It keeps its own outcome, whichever attempt
finishes first.

An attempt whose worker really was killed never reports, so it stays `timeout` with no
duration.

If a slow, superseded attempt releases itself, its row is left as it is rather than
parked as `queued`. The next delivery continues on the newer attempt's row, so a
parked row would stay queued forever.

> **Avoid redelivering running jobs.** A slow job that is redelivered runs twice. Set
> the queue's `retry_after` (or the SQS visibility timeout) longer than the longest job
> can take, and keep the worker `--timeout` below it. See the
> [Horizon guide](../guides/horizon) for the same rule under Horizon.

## How Attempts Are Matched

Each outcome is written to the row matching both the job key and the attempt number
the worker is running. The worker's attempt number is `Job::attempts()`:

| Driver | `attempts()` comes from |
|---|---|
| Redis | The payload's `attempts`, which the reserve script increments. `RedisJob` reports the count from before this reservation plus one. |
| SQS | The message's `ApproximateReceiveCount` attribute |
| Database | The `attempts` column, incremented when the job is reserved |
| Beanstalkd | The job's `reserves` count, read from the server |
| Sync | Always `1` |

If no row carries that attempt number, the outcome goes to the job's latest attempt,
which is how earlier versions matched every outcome. This happens when the row
was pruned, the broker over-reported a count, or a custom repository cannot look
attempts up.

Two sources do not carry an attempt number of their own:

- **Per-job metrics.** laravel-queue-metrics raises `JobMetricsCompleted` and
  `JobMetricsFailed` with the job key only. Both are raised inside the worker that ran
  the job. When a job starts, Queue Monitor remembers in that worker process which
  attempt it began, and uses that attempt when the metrics arrive. If the process never
  saw the start, it falls back to the attempt that is still `processing`.
- **Beanstalkd after deletion.** Beanstalkd's `attempts()` asks the server, which fails
  once a finished job has been deleted. The attempt this worker remembered at the start
  is used instead.

## Stuck Jobs

A stuck job is a row that has been `processing` for longer than
`health.stuck_job_minutes`. Resolving one marks it `timeout`, and with the retry
action also replays it. See [Stuck Jobs](api-reference#stuck-jobs).

Redelivery and stuck-job resolution do not get in each other's way:

- An attempt closed as `timeout` by a redelivery is no longer `processing`. The
  health check does not count it as stuck, and the resolver leaves it alone.
- A stuck row you resolve is also an inferred outcome. If the job turns out to have
  been slow and finishes later, its real outcome replaces the `timeout` in the same way.

## Custom Repositories

Attempt lookup lives in its own contract,
`Cbox\LaravelQueueMonitor\Repositories\Contracts\JobAttemptRepositoryContract`, beside
`JobMonitorRepositoryContract`. Adding a method to `JobMonitorRepositoryContract` would
break every host that implements it. The default `EloquentJobMonitorRepository`
implements both contracts.

A custom repository that implements only `JobMonitorRepositoryContract` keeps working,
and outcomes go to the latest attempt as before. To get per-attempt matching, implement
the second contract as well:

```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobAttemptRepositoryContract;
use Cbox\LaravelQueueMonitor\Repositories\Contracts\JobMonitorRepositoryContract;

class CustomJobMonitorRepository implements JobAttemptRepositoryContract, JobMonitorRepositoryContract
{
    public function findAttemptByJobId(int|string $jobId, int $attempt): ?JobMonitor
    {
        return JobMonitor::where('job_id', (string) $jobId)
            ->where('attempt', $attempt)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    // ... the JobMonitorRepositoryContract methods
}
```
