<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Utilities;

class PayloadRedactor
{
    /**
     * Mask sensitive values in the payload array
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
     * Check if a key is considered sensitive
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
