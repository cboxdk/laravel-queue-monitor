<?php

declare(strict_types=1);

it('loads package migrations by default', function () {
    $paths = $this->app->make('migrator')->paths();

    $packageMigrations = array_filter(
        $paths,
        fn (string $path): bool => str_contains($path, 'create_queue_monitor_jobs_table')
    );

    expect($packageMigrations)->not->toBeEmpty();
});
