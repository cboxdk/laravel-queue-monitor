<?php

declare(strict_types=1);

/**
 * @return array<string, mixed>
 */
function queueMonitorConfigWithPayloadEnv(?string $appEnv, ?string $storePayload): array
{
    $keys = [
        'APP_ENV' => $appEnv,
        'QUEUE_MONITOR_STORE_PAYLOAD' => $storePayload,
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
    $config = queueMonitorConfigWithPayloadEnv('local', null);

    expect($config['storage']['store_payload'])->toBeTrue();
});

test('payload storage defaults off for production environments', function () {
    $config = queueMonitorConfigWithPayloadEnv('production', null);

    expect($config['storage']['store_payload'])->toBeFalse();
});

test('explicit payload storage env enables storage in production', function () {
    $config = queueMonitorConfigWithPayloadEnv('production', 'true');

    expect($config['storage']['store_payload'])->toBeTrue();
});

test('explicit payload storage env disables storage in local environments', function () {
    $config = queueMonitorConfigWithPayloadEnv('local', 'false');

    expect($config['storage']['store_payload'])->toBeFalse();
});
