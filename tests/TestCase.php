<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests;

use Cbox\LaravelQueueMonitor\LaravelQueueMonitor;
use Cbox\LaravelQueueMonitor\LaravelQueueMonitorServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Cbox\\LaravelQueueMonitor\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Allow all requests in test environment
        LaravelQueueMonitor::auth(fn () => true);
    }

    protected function tearDown(): void
    {
        LaravelQueueMonitor::$authUsing = null;

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelQueueMonitorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:6Cu761yZbWosSbaS7vB1/9/3+79Y35kO2P0V+A4F4A4=');
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('queue-monitor.enabled', true);
        config()->set('queue-monitor.storage.store_payload', true);
        config()->set('queue-monitor.api.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Create the cache table needed by statistics caching
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
            Schema::create('cache_locks', function (Blueprint $table): void {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }
}
