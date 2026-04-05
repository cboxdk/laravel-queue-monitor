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
