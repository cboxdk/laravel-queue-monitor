<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Utilities\DatabaseExpressionHelper;

test('minute bucket expressions are generated for supported database drivers', function (string $driver, string $expected): void {
    expect(DatabaseExpressionHelper::minuteBucket('queued_at', $driver))->toBe($expected);
})->with([
    'sqlite' => ['sqlite', "strftime('%Y-%m-%d %H:%M', queued_at)"],
    'mysql' => ['mysql', "DATE_FORMAT(queued_at, '%Y-%m-%d %H:%i')"],
    'mariadb' => ['mariadb', "DATE_FORMAT(queued_at, '%Y-%m-%d %H:%i')"],
    'pgsql' => ['pgsql', "to_char(date_trunc('minute', queued_at), 'YYYY-MM-DD HH24:MI')"],
    'sqlsrv' => ['sqlsrv', 'CONVERT(varchar(16), queued_at, 120)'],
]);

test('hour bucket expressions are generated for supported database drivers', function (string $driver, string $expected): void {
    expect(DatabaseExpressionHelper::hourBucket('queued_at', $driver))->toBe($expected);
})->with([
    'sqlite' => ['sqlite', "strftime('%Y-%m-%d %H:00', queued_at)"],
    'mysql' => ['mysql', "DATE_FORMAT(queued_at, '%Y-%m-%d %H:00')"],
    'mariadb' => ['mariadb', "DATE_FORMAT(queued_at, '%Y-%m-%d %H:00')"],
    'pgsql' => ['pgsql', "to_char(date_trunc('hour', queued_at), 'YYYY-MM-DD HH24:00')"],
    'sqlsrv' => ['sqlsrv', "CONVERT(varchar(13), queued_at, 120) + ':00'"],
]);

test('seconds between expressions are generated for supported database drivers', function (string $driver, string $expected): void {
    expect(DatabaseExpressionHelper::secondsBetween('COALESCE(available_at, queued_at)', 'started_at', $driver))->toBe($expected);
})->with([
    'sqlite' => ['sqlite', '(julianday(started_at) - julianday(COALESCE(available_at, queued_at))) * 86400'],
    'mysql' => ['mysql', 'TIMESTAMPDIFF(SECOND, COALESCE(available_at, queued_at), started_at)'],
    'mariadb' => ['mariadb', 'TIMESTAMPDIFF(SECOND, COALESCE(available_at, queued_at), started_at)'],
    'pgsql' => ['pgsql', 'EXTRACT(EPOCH FROM (started_at - COALESCE(available_at, queued_at)))'],
    'sqlsrv' => ['sqlsrv', 'DATEDIFF(SECOND, COALESCE(available_at, queued_at), started_at)'],
]);

test('unsupported database drivers fail explicitly', function (): void {
    DatabaseExpressionHelper::minuteBucket('queued_at', 'firebird');
})->throws(InvalidArgumentException::class, 'Unsupported queue monitor database driver [firebird].');
