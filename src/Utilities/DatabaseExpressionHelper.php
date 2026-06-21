<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Utilities;

use InvalidArgumentException;

final class DatabaseExpressionHelper
{
    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function minuteBucket(string $column, string $driver): string
    {
        return match (self::normalizeDriver($driver)) {
            'sqlite' => "strftime('%Y-%m-%d %H:%M', {$column})",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m-%d %H:%i')",
            'pgsql' => "to_char(date_trunc('minute', {$column}), 'YYYY-MM-DD HH24:MI')",
            'sqlsrv' => "CONVERT(varchar(16), {$column}, 120)",
            default => throw self::unsupportedDriver($driver),
        };
    }

    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function hourBucket(string $column, string $driver): string
    {
        return match (self::normalizeDriver($driver)) {
            'sqlite' => "strftime('%Y-%m-%d %H:00', {$column})",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00')",
            'pgsql' => "to_char(date_trunc('hour', {$column}), 'YYYY-MM-DD HH24:00')",
            'sqlsrv' => "CONVERT(varchar(13), {$column}, 120) + ':00'",
            default => throw self::unsupportedDriver($driver),
        };
    }

    /**
     * @param  literal-string  $fromExpression
     * @param  literal-string  $toExpression
     * @return literal-string
     */
    public static function secondsBetween(string $fromExpression, string $toExpression, string $driver): string
    {
        return match (self::normalizeDriver($driver)) {
            'sqlite' => "(julianday({$toExpression}) - julianday({$fromExpression})) * 86400",
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, {$fromExpression}, {$toExpression})",
            'pgsql' => "EXTRACT(EPOCH FROM ({$toExpression} - {$fromExpression}))",
            'sqlsrv' => "DATEDIFF(SECOND, {$fromExpression}, {$toExpression})",
            default => throw self::unsupportedDriver($driver),
        };
    }

    private static function normalizeDriver(string $driver): string
    {
        return strtolower($driver);
    }

    private static function unsupportedDriver(string $driver): InvalidArgumentException
    {
        return new InvalidArgumentException("Unsupported queue monitor database driver [{$driver}].");
    }
}
