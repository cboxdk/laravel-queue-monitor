<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Utilities;

final class PayloadRedactor
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
            if (is_string($key) && self::isSensitive($key, $sensitiveKeys)) {
                $payload[$key] = '*****';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = self::redact($value, $sensitiveKeys);

                continue;
            }
        }

        return $payload;
    }

    /**
     * Redact sensitive info from exception traces (file paths, credentials in stack frames).
     *
     * Strips the application base path to show relative paths only, and masks
     * the value after any configured sensitive key (e.g. arguments embedded in
     * the trace when zend.exception_ignore_args is off).
     *
     * @param  array<string>  $sensitiveKeys
     */
    public static function redactTrace(?string $trace, array $sensitiveKeys = []): ?string
    {
        if ($trace === null) {
            return null;
        }

        $basePath = base_path().'/';
        $trace = str_replace($basePath, '', $trace);

        return self::redactString($trace, $sensitiveKeys);
    }

    /**
     * Mask the value that follows any configured sensitive key inside a free
     * text string (an exception message or trace) — e.g. `password=secret`,
     * `token: sk_live_x`, or `api_key => "abc"` become `password=*****` etc.
     * The key and separator are preserved; only the value token is masked.
     *
     * @param  array<string>  $sensitiveKeys
     */
    public static function redactString(?string $value, array $sensitiveKeys): ?string
    {
        if ($value === null || $value === '' || $sensitiveKeys === []) {
            return $value;
        }

        foreach ($sensitiveKeys as $sensitiveKey) {
            $key = preg_quote($sensitiveKey, '/');
            $replaced = preg_replace(
                '/('.$key.'["\']?\s*(?:=>|[=:])\s*["\']?)([^\s"\',;)&]+)/i',
                '${1}*****',
                $value
            );

            if ($replaced !== null) {
                $value = $replaced;
            }
        }

        return $value;
    }

    /**
     * Check if a key is considered sensitive
     *
     * @param  array<string>  $sensitiveKeys
     */
    public static function isSensitive(string $key, array $sensitiveKeys): bool
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
