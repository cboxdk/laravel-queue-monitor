<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Utilities;

class PayloadRedactor
{
    /**
     * Mask sensitive values in the payload array
     *
     * @param  array<mixed, mixed>  $payload
     * @param  array<string>  $sensitiveKeys
     * @return array<mixed, mixed>
     */
    public static function redact(array $payload, array $sensitiveKeys): array
    {
        if (empty($sensitiveKeys)) {
            return $payload;
        }

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = self::redact($value, $sensitiveKeys);

                continue;
            }

            if (is_string($key) && self::isSensitive($key, $sensitiveKeys)) {
                $payload[$key] = '*****';
            }
        }

        return $payload;
    }

    /**
     * Redact sensitive info from exception traces (file paths, credentials in stack frames).
     *
     * Strips the application base path to show relative paths only.
     */
    public static function redactTrace(?string $trace): ?string
    {
        if ($trace === null) {
            return null;
        }

        $basePath = base_path().'/';

        return str_replace($basePath, '', $trace);
    }

    /**
     * Check if a key is considered sensitive
     *
     * @param  array<string>  $sensitiveKeys
     */
    private static function isSensitive(string $key, array $sensitiveKeys): bool
    {
        $normalizedKey = strtolower($key);

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains($normalizedKey, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }
}
