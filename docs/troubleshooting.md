---
title: "Troubleshooting"
description: "Comprehensive troubleshooting guide with solutions for common issues and debugging tips"
weight: 85
---

# Troubleshooting

## Common Issues

### Jobs Not Being Tracked

**Problem**: Jobs are running but not appearing in queue monitor.

**Solutions**:

1. **Check if monitoring is enabled**:
```php
config('queue-monitor.enabled'); // Should be true
```

2. **Verify migrations ran**:
```bash
php artisan migrate:status | grep queue_monitor
```

3. **Check event listeners are registered**:
```php
// In tinker
Event::hasListeners(\Illuminate\Queue\Events\JobQueued::class);
// Should return true
```

4. **Clear config cache**:
```bash
php artisan config:clear
php artisan cache:clear
```

5. **Check database connection**:
```php
// Verify the connection exists
config('queue-monitor.database.connection'); // Should be valid or null
```

### Payload Not Stored

**Problem**: Jobs are tracked but payload is null, can't replay.

**Solutions**:

1. **Enable payload storage**:
```php
// config/queue-monitor.php
'storage' => [
    'store_payload' => true,
],

// Or via environment
QUEUE_MONITOR_STORE_PAYLOAD=true
```

2. **Check payload size limit**:
```php
use Cbox\LaravelQueueMonitor\Utilities\JobPayloadSerializer;

$payload = ['large' => 'data'];

if (JobPayloadSerializer::exceedsSizeLimit($payload)) {
    // Payload too large, increase limit
    config(['queue-monitor.storage.payload_max_size' => 131072]); // 128KB
}
```

3. **Verify serialization works**:
```php
$job = new YourJob($data);

try {
    $serialized = serialize($job);
    $unserialized = unserialize($serialized);
    // Should work without errors
} catch (\Exception $e) {
    // Job has non-serializable properties
    echo $e->getMessage();
}
```

### Replay Fails

**Problem**: Job replay throws exceptions.

**Common Errors and Solutions**:

**"Job class no longer exists"**
```php
// Solution: Ensure job class is still in codebase
class_exists('App\\Jobs\\OldJob'); // Check if exists

// If deleted, you can't replay. Clean up old jobs:
JobMonitor::where('job_class', 'App\\Jobs\\OldJob')->delete();
```

**"Cannot replay job that is currently processing"**
```php
// Solution: Wait for job to finish or mark as failed
$job = JobMonitor::findByUuid($uuid);

if ($job->status === JobStatus::PROCESSING) {
    // Job stuck? Update manually:
    $job->update([
        'status' => JobStatus::FAILED,
        'completed_at' => now(),
    ]);
}
```

**"Payload not stored"**
```php
// Solution: Enable storage before queuing future jobs
config(['queue-monitor.storage.store_payload' => true]);

// For existing jobs without payload, they can't be replayed
```

### High Database Usage

**Problem**: queue_monitor_jobs table growing too large.

**Solutions**:

1. **Enable automatic pruning**:
```php
// routes/console.php
Schedule::command('queue-monitor:prune', ['--days' => 7])
    ->daily();
```

2. **Prune more aggressively**:
```bash
# Keep only failed jobs, delete successful ones after 1 day
php artisan queue-monitor:prune --days=1 --statuses=completed
```

3. **Use separate database**:
```php
// config/queue-monitor.php
'database' => [
    'connection' => 'mysql_analytics', // Separate database
],
```

4. **Disable payload storage**:
```php
// Saves significant space if replay not needed
'storage' => [
    'store_payload' => false,
],
```

### Performance Issues

**Problem**: Queue processing seems slower with monitoring enabled.

**Solutions**:

1. **Check database indexes**:
```sql
-- Verify indexes exist
SHOW INDEXES FROM queue_monitor_jobs;

-- Should see indexes on: uuid, job_id, status, queue, etc.
```

2. **Optimize database connection**:
```php
// Use persistent connections
config(['queue-monitor.database.connection' => 'mysql_persistent']);
```

3. **Reduce payload size**:
```php
// In your jobs
public function __sleep(): array
{
    // Only serialize essential properties
    return ['userId', 'data'];
}
```

4. **Silent failure is working**:
```php
// Monitoring errors are reported but don't break queues
// Check logs for any issues:
tail -f storage/logs/laravel.log | grep queue-monitor
```

### API Returns Empty Data

**Problem**: API endpoints return empty arrays or null.

**Solutions**:

1. **Check API is enabled**:
```php
config('queue-monitor.api.enabled'); // Should be true
```

2. **Verify middleware allows access**:
```php
// config/queue-monitor.php
'api' => [
    'middleware' => ['api'], // Check middleware chain
],
```

3. **Test database has data**:
```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

JobMonitor::count(); // Should be > 0
```

4. **Check route is loaded**:
```bash
php artisan route:list | grep queue-monitor
# Should show all API routes
```

### Horizon Not Detected

**Problem**: All jobs show as `queue_work` even when using Horizon.

**Solutions**:

1. **Enable Horizon detection**:
```php
// config/queue-monitor.php
'worker_detection' => [
    'horizon_detection' => true,
],
```

2. **Check queue-metrics is installed**:
```bash
composer show cboxdk/laravel-queue-metrics
```

3. **Verify Horizon is actually running**:
```bash
php artisan horizon:status
```

4. **Check environment variables**:
```bash
# Horizon sets this variable
echo $HORIZON_SUPERVISOR
```

### Memory Metrics Not Captured

**Problem**: memory_peak_mb is always null.

**Solutions**:

1. **Check job completes successfully**:
```php
// Memory is only captured on completion
$job = JobMonitor::find($id);
if ($job->status !== JobStatus::COMPLETED) {
    // Job didn't complete, no metrics
}
```

2. **Verify PHP memory tracking**:
```php
// Test in tinker
memory_get_peak_usage(true); // Should return a number
```

3. **Jobs complete too quickly**:
```php
// Very fast jobs may show minimal memory
// This is expected behavior
```

### Tags Not Showing

**Problem**: Jobs tracked but tags not visible.

**Solutions**:

1. **Implement tags() method**:
```php
class YourJob implements ShouldQueue
{
    public function tags(): array
    {
        return ['category', 'priority'];
    }
}
```

2. **Check tag normalization**:
```php
use Cbox\LaravelQueueMonitor\Models\Tag;

Tag::count(); // Should match jobs with tags

// Manually trigger tag storage if needed
$job = JobMonitor::find($id);
if ($job->tags && Tag::where('job_id', $id)->count() === 0) {
    app(\Cbox\LaravelQueueMonitor\Repositories\Contracts\TagRepositoryContract::class)
        ->storeTags($id, $job->tags);
}
```

## Debugging Tips

### Enable Debug Logging

```php
// In a service provider or listener
Event::listen('*', function($event, $data) {
    if (str_contains($event, 'Job')) {
        Log::debug('Queue Event', [
            'event' => $event,
            'data' => $data,
        ]);
    }
});
```

### Check Listener Execution

```php
use Cbox\LaravelQueueMonitor\Events\JobMonitorRecorded;

Event::listen(JobMonitorRecorded::class, function($event) {
    Log::info('Job monitored', [
        'uuid' => $event->jobMonitor->uuid,
        'status' => $event->jobMonitor->status->value,
    ]);
});
```

### Verify Database Structure

```bash
# Check tables exist
php artisan db:table queue_monitor_jobs
php artisan db:table queue_monitor_tags

# Check row counts
php artisan tinker
>>> Cbox\LaravelQueueMonitor\Models\JobMonitor::count();
```

### Test Event Flow

```php
// Manually trigger events to test listeners
use Illuminate\Queue\Events\JobQueued;

$job = new App\Jobs\TestJob;
event(new JobQueued('redis', $job));

// Check if job monitor was created
JobMonitor::latest()->first();
```

## Performance Optimization

### Slow Queries

**Problem**: Statistics queries are slow.

**Solutions**:

1. **Add additional indexes**:
```php
// Create migration
Schema::table('queue_monitor_jobs', function($table) {
    $table->index(['job_class', 'completed_at']);
    $table->index(['queue', 'completed_at']);
});
```

2. **Cache statistics**:
```php
use Illuminate\Support\Facades\Cache;

$stats = Cache::remember('queue-monitor:stats', 300, function() {
    return QueueMonitor::statistics();
});
```

3. **Use database views**:
```sql
CREATE VIEW queue_monitor_summary AS
SELECT
    job_class,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    AVG(duration_ms) as avg_duration
FROM queue_monitor_jobs
GROUP BY job_class;
```

### High Write Load

**Problem**: Too many database writes impacting performance.

**Solutions**:

1. **Batch tag insertion**:
```php
// Already implemented in TagRepository::storeTags()
// Uses single INSERT for all tags
```

2. **Use queue for pruning**:
```php
// Instead of synchronous pruning
dispatch(new PruneOldJobsJob);
```

3. **Reduce index count**:
```php
// Remove unused indexes from migration
// Keep only indexes for your query patterns
```

## Data Integrity

### Orphaned Tags

**Problem**: Tags exist without corresponding jobs.

**Solution**:
```bash
# Tags cascade on delete, but if manually deleted:
DELETE t FROM queue_monitor_tags t
LEFT JOIN queue_monitor_jobs j ON t.job_id = j.id
WHERE j.id IS NULL;
```

### Stuck Processing Jobs

**Problem**: Jobs stuck in "processing" status.

**Solution**:
```php
// Find stuck jobs (processing > 1 hour)
$stuck = JobMonitor::where('status', JobStatus::PROCESSING)
    ->where('started_at', '<', now()->subHour())
    ->get();

// Mark as failed
foreach ($stuck as $job) {
    $job->update([
        'status' => JobStatus::TIMEOUT,
        'completed_at' => now(),
        'exception_message' => 'Job stuck, marked as timeout',
    ]);
}
```

### Duplicate Job Records

**Problem**: Multiple records for same job.

**Solution**:
```php
// This shouldn't happen, but if it does:
$duplicates = JobMonitor::select('job_id')
    ->whereNotNull('job_id')
    ->groupBy('job_id')
    ->havingRaw('COUNT(*) > 1')
    ->get();

// Keep only latest record
foreach ($duplicates as $dup) {
    JobMonitor::where('job_id', $dup->job_id)
        ->orderBy('created_at')
        ->skip(1)
        ->delete();
}
```

## Configuration Issues

### Wrong Worker Type Detected

**Problem**: Horizon jobs showing as queue_work.

**Solution**:
```php
// Test Horizon detection
use Cbox\LaravelQueueMetrics\Utilities\HorizonDetector;

$context = HorizonDetector::detect();
dd($context->isHorizon, $context->supervisorName);

// If false but should be true, check Horizon is running:
php artisan horizon:status
```

### Server Name Wrong

**Problem**: Server name shows as "unknown" or incorrect hostname.

**Solution**:
```php
// Provide custom server name
'worker_detection' => [
    'server_name_callable' => function() {
        return config('app.server_name') ?? gethostname();
    },
],
```

## Testing Issues

### Tests Fail with Migration Errors

**Problem**: Tests can't run migrations.

**Solution**:
```php
// Ensure TestCase loads migrations
protected function defineDatabaseMigrations(): void
{
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
}
```

### Factory Errors

**Problem**: JobMonitor::factory() doesn't work.

**Solution**:
```php
// Check factory namespace in TestCase
Factory::guessFactoryNamesUsing(
    fn (string $modelName) => 'Cbox\\LaravelQueueMonitor\\Database\\Factories\\'.class_basename($modelName).'Factory'
);
```

## Getting Help

### Enable Verbose Logging

```php
// Add to a service provider
if (app()->environment('local')) {
    Event::listen('*', function($eventName, $data) {
        if (str_contains($eventName, 'Job') || str_contains($eventName, 'Queue')) {
            Log::debug('Event fired', [
                'event' => $eventName,
                'time' => now()->toDateTimeString(),
            ]);
        }
    });
}
```

### Check Package Version

```bash
composer show cboxdk/laravel-queue-monitor
composer show cboxdk/laravel-queue-metrics
```

### Verify Configuration

```php
// Dump entire config
php artisan tinker
>>> config('queue-monitor');
```

### Database Query Logging

```php
// Enable query log temporarily
DB::enableQueryLog();

// Run operation
QueueMonitor::statistics();

// Check queries
dd(DB::getQueryLog());
```

## FAQ

**Q: Does monitoring slow down queue processing?**

A: Minimal impact (<1% overhead). The package uses:
- Asynchronous event listeners
- Efficient database queries
- Silent failure to never block queues
- Strategic indexes for performance

**Q: Can I use this without laravel-queue-metrics?**

A: No, it's a hard dependency. But you get enhanced metrics (CPU, memory) in return.

**Q: How much disk space does it use?**

A: Depends on:
- Job volume
- Payload size
- Retention period

Example: 10,000 jobs/day with 5KB payloads = ~50MB/day
Use pruning to manage: `queue-monitor:prune --days=7`

**Q: Can I disable monitoring temporarily?**

A: Yes:
```bash
QUEUE_MONITOR_ENABLED=false
```
Or:
```php
config(['queue-monitor.enabled' => false]);
```

**Q: Does it work with all queue drivers?**

A: Yes! Works with database, redis, SQS, Beanstalkd, etc.
Monitoring happens at the Laravel event layer, not driver layer.

**Q: Can I monitor only specific queues?**

A: Not directly, but you can:
```php
Event::listen(JobQueued::class, function($event) {
    if (in_array($event->job->getQueue(), ['important', 'critical'])) {
        // Only track these queues
        // Implement custom logic
    }
});
```

**Q: How do I export job data?**

A: Use the API or Eloquent:
```php
use Cbox\LaravelQueueMonitor\Models\JobMonitor;

$jobs = JobMonitor::whereDate('created_at', today())->get();

// Export to CSV
$csv = $jobs->map(fn($job) => [
    $job->uuid,
    $job->job_class,
    $job->status->value,
    $job->duration_ms,
])->toArray();

file_put_contents('jobs.csv', collect($csv)->toCsv());
```

**Q: Can I track custom metrics?**

A: Yes, extend the package:
```php
// Custom action
class RecordCustomMetricsAction
{
    public function execute(string $uuid, array $customMetrics): void
    {
        $job = JobMonitor::where('uuid', $uuid)->first();

        // Store in JSON column or separate table
        $job->update(['custom_metrics' => $customMetrics]);
    }
}
```

**Q: How do I monitor scheduled jobs?**

A: Scheduled jobs become queue jobs:
```php
// When using ->onQueue()
Schedule::command('app:process')->daily()->onQueue('scheduled');

// These appear in queue monitor
$scheduled = JobMonitor::onQueue('scheduled')->get();
```

## Error Messages Explained

### "Queue monitor is disabled"

Monitoring is turned off via configuration.

**Fix**: Set `QUEUE_MONITOR_ENABLED=true` in `.env`

### "Job payload not stored, cannot replay"

Payload storage was disabled when job ran.

**Fix**: Enable for future jobs, this job cannot be replayed.

### "Job class {class} no longer exists"

The job class has been deleted or renamed.

**Fix**: Restore the class or delete old job records.

### "Failed to encode payload to JSON"

Payload contains non-serializable data.

**Fix**: Ensure all job properties are serializable.

## Best Practices to Avoid Issues

1. **Always test locally first** before deploying monitoring changes
2. **Use pruning** to prevent database bloat
3. **Monitor the monitor** - track queue-monitor's own performance
4. **Version your jobs** - avoid breaking changes to job classes
5. **Cache statistics** - don't query real-time for dashboards
6. **Use tags liberally** - makes filtering much easier
7. **Set up alerts** - for failed jobs, not just monitoring
8. **Regular backups** - especially if using as audit trail
9. **Test replay in staging** - before using in production
10. **Document custom configurations** - for team members

## Still Need Help?

1. Check [GitHub Issues](https://github.com/cboxdk/laravel-queue-monitor/issues)
2. Review [Architecture Documentation](architecture)
3. Check [Queue-Metrics Integration](metrics-integration)
4. Read [Advanced Usage](advanced-usage) for custom solutions
