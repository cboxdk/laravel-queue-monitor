<?php

declare(strict_types=1);

namespace PHPeek\LaravelQueueMonitor;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobTimedOut;
use Illuminate\Support\Facades\Event;
use PHPeek\LaravelQueueMonitor\Commands\HealthCheckCommand;
use PHPeek\LaravelQueueMonitor\Commands\LaravelQueueMonitorCommand;
use PHPeek\LaravelQueueMonitor\Commands\PruneJobsCommand;
use PHPeek\LaravelQueueMonitor\Commands\ReplayJobCommand;
use PHPeek\LaravelQueueMonitor\Listeners\JobFailedListener;
use PHPeek\LaravelQueueMonitor\Listeners\JobProcessedListener;
use PHPeek\LaravelQueueMonitor\Listeners\JobProcessingListener;
use PHPeek\LaravelQueueMonitor\Listeners\JobQueuedListener;
use PHPeek\LaravelQueueMonitor\Listeners\JobTimedOutListener;
use PHPeek\LaravelQueueMonitor\Listeners\QueueMetricsSubscriber;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelQueueMonitorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-queue-monitor')
            ->hasConfigFile('queue-monitor')
            ->hasMigration('create_queue_monitor_jobs_table')
            ->hasCommands([
                LaravelQueueMonitorCommand::class,
                PruneJobsCommand::class,
                ReplayJobCommand::class,
                HealthCheckCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(LaravelQueueMonitor::class);
    }

    public function packageBooted(): void
    {
        // Order matters: repositories and actions must be bound before event listeners
        // because listeners depend on action classes which depend on repositories
        $this->registerRepositories();
        $this->registerActions();
        $this->registerEventListeners();
        $this->registerRoutes();
    }

    /**
     * Register API routes
     */
    protected function registerRoutes(): void
    {
        if (! config('queue-monitor.api.enabled', false)) {
            return;
        }

        if (file_exists(__DIR__.'/../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
    }

    /**
     * Register event listeners
     */
    protected function registerEventListeners(): void
    {
        if (! config('queue-monitor.enabled', true)) {
            return;
        }

        // Register Laravel Queue event listeners
        Event::listen(JobQueued::class, JobQueuedListener::class);
        Event::listen(JobProcessing::class, JobProcessingListener::class);
        Event::listen(JobProcessed::class, JobProcessedListener::class);
        Event::listen(JobFailed::class, JobFailedListener::class);
        Event::listen(JobTimedOut::class, JobTimedOutListener::class);

        // Register queue-metrics event subscriber
        Event::subscribe(QueueMetricsSubscriber::class);
    }

    /**
     * Register repository bindings
     */
    protected function registerRepositories(): void
    {
        $repositories = config('queue-monitor.repositories', []);

        foreach ($repositories as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
    }

    /**
     * Register action bindings
     */
    protected function registerActions(): void
    {
        $actions = config('queue-monitor.actions', []);

        foreach ($actions as $key => $implementation) {
            $this->app->singleton($implementation);
        }
    }
}
