<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function queueMonitorConfigWithEnv(?string $appEnv, ?string $storePayload = null, ?string $apiEnabled = null): array
{
    $keys = [
        'APP_ENV' => $appEnv,
        'QUEUE_MONITOR_STORE_PAYLOAD' => $storePayload,
        'QUEUE_MONITOR_API_ENABLED' => $apiEnabled,
    ];

    $original = [];

    foreach ($keys as $key => $value) {
        $original[$key] = [
            'env' => $_ENV[$key] ?? null,
            'server' => $_SERVER[$key] ?? null,
            'getenv' => getenv($key),
        ];

        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            continue;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    try {
        $config = require dirname(__DIR__, 3).'/config/queue-monitor.php';

        if (! is_array($config)) {
            throw new RuntimeException('Queue Monitor config did not return an array.');
        }

        return $config;
    } finally {
        foreach ($original as $key => $values) {
            if ($values['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $values['env'];
            }

            if ($values['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $values['server'];
            }

            if ($values['getenv'] === false) {
                putenv($key);
            } else {
                putenv($key.'='.$values['getenv']);
            }
        }
    }
}

test('payload storage defaults on for local environments', function () {
    $config = queueMonitorConfigWithEnv('local');

    expect($config['storage']['store_payload'])->toBeTrue();
});

test('payload storage defaults off for production environments', function () {
    $config = queueMonitorConfigWithEnv('production');

    expect($config['storage']['store_payload'])->toBeFalse();
});

test('explicit payload storage env enables storage in production', function () {
    $config = queueMonitorConfigWithEnv('production', 'true');

    expect($config['storage']['store_payload'])->toBeTrue();
});

test('explicit payload storage env disables storage in local environments', function () {
    $config = queueMonitorConfigWithEnv('local', 'false');

    expect($config['storage']['store_payload'])->toBeFalse();
});

test('api defaults on for local environments', function () {
    $config = queueMonitorConfigWithEnv('local');

    expect($config['api']['enabled'])->toBeTrue();
});

test('api defaults off for production environments', function () {
    $config = queueMonitorConfigWithEnv('production');

    expect($config['api']['enabled'])->toBeFalse();
});

test('explicit api env enables api in production', function () {
    $config = queueMonitorConfigWithEnv('production', null, 'true');

    expect($config['api']['enabled'])->toBeTrue();
});

test('explicit api env disables api in local environments', function () {
    $config = queueMonitorConfigWithEnv('local', null, 'false');

    expect($config['api']['enabled'])->toBeFalse();
});

test('api response redaction masks serialized commands by default', function () {
    $config = queueMonitorConfigWithEnv('local');

    expect($config['api']['sensitive_keys'])->toContain('command');
});

test('api and export limits have safe defaults', function () {
    $config = queueMonitorConfigWithEnv('local');

    expect($config['api']['default_limit'])->toBe(50);
    expect($config['api']['max_limit'])->toBe(1000);
    expect($config['export']['default_limit'])->toBe(1000);
    expect($config['export']['max_rows'])->toBe(5000);
    expect($config['batch']['chunk_size'])->toBe(100);
    expect($config['batch']['max_jobs'])->toBe(1000);
});
