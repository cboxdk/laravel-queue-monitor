<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Tests\Support\ExampleJob;
use Cbox\LaravelQueueMonitor\Tests\Support\ListenedEvent;
use Cbox\LaravelQueueMonitor\Tests\Support\PlainJob;
use Cbox\LaravelQueueMonitor\Tests\Support\QueuedListener;
use Cbox\LaravelQueueMonitor\Tests\Support\QueuedMailable;
use Cbox\LaravelQueueMonitor\Tests\Support\QueuedNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * job_class is the FQCN of the queued command and display_name is what the
 * command's displayName() reports, whichever path created the row:
 *
 * - `sync`: SyncQueue runs the job inline without raising JobQueued, so the row
 *   is created on JobProcessing from the queue Job's payload;
 * - `database`: the row is created on JobQueued from the command object, then
 *   updated when a real worker processes it.
 *
 * Before the fix the sync path recorded the payload displayName as job_class,
 * so a job with a custom displayName() appeared under a "class" that does not
 * exist and split from its own queued rows in every per-class view.
 */
beforeEach(function () {
    Schema::create('jobs', function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });

    config()->set('queue.connections.database', [
        'driver' => 'database',
        'connection' => 'testing',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => false,
    ]);
    config()->set('mail.default', 'array');

    Notification::extend('monitor-test', fn () => new class
    {
        public function send(object $notifiable, object $notification): void
        {
            //
        }
    });
});

dataset('connections', [
    'sync (row created on processing)' => ['sync'],
    'database (row created on queueing)' => ['database'],
]);

/**
 * Run whatever is on the connection to completion, so the database path also
 * goes through the processing and completion recording.
 */
function drainQueue(string $connection): void
{
    if ($connection === 'database') {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);
    }
}

function onlyMonitorRow(): JobMonitor
{
    expect(JobMonitor::count())->toBe(1);

    /** @var JobMonitor */
    return JobMonitor::query()->firstOrFail();
}

test('a job with a custom displayName records its class as job_class', function (string $connection) {
    config()->set('queue.default', $connection);

    dispatch(new ExampleJob);
    drainQueue($connection);

    $row = onlyMonitorRow();

    expect($row->job_class)->toBe(ExampleJob::class)
        ->and($row->display_name)->toBe('Example Job for Testing')
        ->and($row->status)->toBe(JobStatus::COMPLETED);
})->with('connections');

test('dispatch_sync records the class, not the display name', function () {
    config()->set('queue.default', 'database');

    // dispatch_sync() bypasses the default connection and always runs inline.
    dispatch_sync(new ExampleJob);

    $row = onlyMonitorRow();

    expect($row->job_class)->toBe(ExampleJob::class)
        ->and($row->display_name)->toBe('Example Job for Testing')
        ->and($row->connection)->toBe('sync');
});

test('a job without displayName records its class as job_class', function (string $connection, ?string $expectedDisplayName) {
    config()->set('queue.default', $connection);

    dispatch(new PlainJob);
    drainQueue($connection);

    $row = onlyMonitorRow();

    // On JobQueued the display name is taken from the command's displayName()
    // only when it defines one; a row created from a queue Job takes the
    // payload displayName, which Laravel sets to the class for such a job.
    expect($row->job_class)->toBe(PlainJob::class)
        ->and($row->display_name)->toBe($expectedDisplayName);
})->with([
    'sync (row created on processing)' => ['sync', PlainJob::class],
    'database (row created on queueing)' => ['database', null],
]);

test('a queued closure records CallQueuedClosure with the closure location as display name', function (string $connection) {
    config()->set('queue.default', $connection);

    dispatch(function (): void {
        //
    });
    drainQueue($connection);

    $row = onlyMonitorRow();

    expect($row->job_class)->toBe(CallQueuedClosure::class)
        ->and($row->display_name)->toStartWith('Closure (JobClassRecordingTest.php:');
})->with('connections');

test('a queued mailable records SendQueuedMailable with the mailable as display name', function (string $connection) {
    config()->set('queue.default', $connection);

    Mail::to('someone@example.com')->queue(new QueuedMailable);
    drainQueue($connection);

    $row = onlyMonitorRow();

    expect($row->job_class)->toBe(SendQueuedMailable::class)
        ->and($row->display_name)->toBe(QueuedMailable::class);
})->with('connections');

test('a queued notification records SendQueuedNotifications with the notification as display name', function (string $connection) {
    config()->set('queue.default', $connection);

    Notification::route('monitor-test', 'someone')->notify(new QueuedNotification);
    drainQueue($connection);

    $row = onlyMonitorRow();

    expect($row->job_class)->toBe(SendQueuedNotifications::class)
        ->and($row->display_name)->toBe(QueuedNotification::class);
})->with('connections');

test('a queued listener records CallQueuedListener with the listener as display name', function (string $connection) {
    config()->set('queue.default', $connection);

    Event::listen(ListenedEvent::class, QueuedListener::class);
    event(new ListenedEvent);
    drainQueue($connection);

    $row = onlyMonitorRow();

    expect($row->job_class)->toBe(CallQueuedListener::class)
        ->and($row->display_name)->toBe(QueuedListener::class);
})->with('connections');
