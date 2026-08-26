<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Tests\Feature;

use Cbox\LaravelQueueMonitor\Tests\TestCase;

class PackageMigrationsOptOutTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        config()->set('queue-monitor.enable_migrations', false);
    }

    public function test_package_migrations_are_not_loaded_when_disabled(): void
    {
        $paths = $this->app->make('migrator')->paths();

        $packageMigrations = array_filter(
            $paths,
            fn (string $path): bool => str_contains($path, 'queue_monitor')
        );

        $this->assertSame([], $packageMigrations);
    }
}
