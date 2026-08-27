<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Utilities\PayloadRedactor;

test('redacts sensitive keys from payload', function () {
    $payload = [
        'name' => 'John',
        'password' => 'secret123',
        'email' => 'john@example.com',
    ];

    $result = PayloadRedactor::redact($payload, ['password']);

    expect($result['name'])->toBe('John');
    expect($result['password'])->toBe('*****');
    expect($result['email'])->toBe('john@example.com');
});

test('redacts nested sensitive keys recursively', function () {
    $payload = [
        'user' => [
            'name' => 'John',
            'credentials' => [
                'password' => 'secret123',
                'api_token' => 'abc123',
            ],
        ],
    ];

    $result = PayloadRedactor::redact($payload, ['password', 'token']);

    expect($result['user']['name'])->toBe('John');
    expect($result['user']['credentials']['password'])->toBe('*****');
    expect($result['user']['credentials']['api_token'])->toBe('*****');
});

test('matching is case-insensitive', function () {
    $payload = [
        'PASSWORD' => 'secret',
        'Api_Token' => 'abc',
        'SECRET_KEY' => 'xyz',
    ];

    $result = PayloadRedactor::redact($payload, ['password', 'token', 'secret']);

    expect($result['PASSWORD'])->toBe('*****');
    expect($result['Api_Token'])->toBe('*****');
    expect($result['SECRET_KEY'])->toBe('*****');
});

test('matches partial key names', function () {
    $payload = [
        'user_password_hash' => 'hashed',
        'authorization_header' => 'Bearer xyz',
        'name' => 'safe',
    ];

    $result = PayloadRedactor::redact($payload, ['password', 'authorization']);

    expect($result['user_password_hash'])->toBe('*****');
    expect($result['authorization_header'])->toBe('*****');
    expect($result['name'])->toBe('safe');
});

test('returns payload unchanged when no sensitive keys provided', function () {
    $payload = ['password' => 'secret', 'name' => 'John'];

    $result = PayloadRedactor::redact($payload, []);

    expect($result)->toBe($payload);
});

test('handles empty payload', function () {
    $result = PayloadRedactor::redact([], ['password']);

    expect($result)->toBe([]);
});

test('does not redact numeric keys', function () {
    $payload = [0 => 'first', 1 => 'second', 'password' => 'secret'];

    $result = PayloadRedactor::redact($payload, ['password']);

    expect($result[0])->toBe('first');
    expect($result[1])->toBe('second');
    expect($result['password'])->toBe('*****');
});

test('handles deeply nested structures', function () {
    $payload = [
        'level1' => [
            'level2' => [
                'level3' => [
                    'secret' => 'deep_secret',
                    'safe' => 'visible',
                ],
            ],
        ],
    ];

    $result = PayloadRedactor::redact($payload, ['secret']);

    expect($result['level1']['level2']['level3']['secret'])->toBe('*****');
    expect($result['level1']['level2']['level3']['safe'])->toBe('visible');
});

test('redacts sensitive array values before traversing nested payloads', function () {
    $payload = [
        'data' => [
            'commandName' => 'App\\Jobs\\ImportUsers',
            'command' => [
                'public' => 'visible',
                'password' => 'secret',
            ],
        ],
    ];

    $result = PayloadRedactor::redact($payload, ['command', 'password']);

    expect($result['data']['commandName'])->toBe('*****');
    expect($result['data']['command'])->toBe('*****');
});

test('redactString masks the value after a sensitive key with various separators', function () {
    $keys = ['password', 'token', 'api_key'];

    expect(PayloadRedactor::redactString('SQLSTATE[HY000]: password=hunter2 failed', $keys))
        ->toBe('SQLSTATE[HY000]: password=***** failed');
    expect(PayloadRedactor::redactString('Auth failed with token: sk_live_abc123', $keys))
        ->toBe('Auth failed with token: *****');
    expect(PayloadRedactor::redactString('config api_key => "abcdef123"', $keys))
        ->toBe('config api_key => "*****"');
});

test('redactString is case-insensitive and leaves non-sensitive text intact', function () {
    expect(PayloadRedactor::redactString('Connection Password=secret on host db1', ['password']))
        ->toBe('Connection Password=***** on host db1');
    expect(PayloadRedactor::redactString('A plain error with no secrets', ['password']))
        ->toBe('A plain error with no secrets');
});

test('redactString returns the value unchanged with no keys or null input', function () {
    expect(PayloadRedactor::redactString('password=secret', []))->toBe('password=secret');
    expect(PayloadRedactor::redactString(null, ['password']))->toBeNull();
});

test('redactTrace strips the base path and masks sensitive values', function () {
    $trace = base_path().'/app/Jobs/Charge.php(42): connect(token=sk_live_xyz)';

    expect(PayloadRedactor::redactTrace($trace, ['token']))
        ->toBe('app/Jobs/Charge.php(42): connect(token=*****)');
});
