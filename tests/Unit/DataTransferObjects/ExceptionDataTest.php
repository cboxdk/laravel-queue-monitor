<?php

declare(strict_types=1);

use PHPeek\LaravelQueueMonitor\DataTransferObjects\ExceptionData;

test('creates from throwable', function () {
    $exception = new RuntimeException('Test error message', 500);

    $data = ExceptionData::fromThrowable($exception);

    expect($data->class)->toBe(RuntimeException::class);
    expect($data->message)->toBe('Test error message');
    expect($data->trace)->toContain('ExceptionDataTest.php');
    expect($data->file)->toContain('ExceptionDataTest.php');
    expect($data->line)->toBeInt();
});

test('creates from array', function () {
    $data = ExceptionData::fromArray([
        'class' => 'App\\Exceptions\\CustomException',
        'message' => 'Something went wrong',
        'trace' => 'Stack trace here',
        'file' => '/path/to/file.php',
        'line' => 42,
    ]);

    expect($data->class)->toBe('App\\Exceptions\\CustomException');
    expect($data->message)->toBe('Something went wrong');
    expect($data->line)->toBe(42);
});

test('converts to array', function () {
    $data = new ExceptionData(
        class: 'App\\Exceptions\\TestException',
        message: 'Error occurred',
        trace: 'Stack trace',
        file: '/test.php',
        line: 10
    );

    $array = $data->toArray();

    expect($array)->toHaveKeys(['class', 'message', 'trace', 'file', 'line']);
    expect($array['class'])->toBe('App\\Exceptions\\TestException');
});

test('shortClass returns class name without namespace', function () {
    $data = new ExceptionData(
        class: 'App\\Exceptions\\CustomException',
        message: 'Test',
        trace: 'Trace'
    );

    expect($data->shortClass())->toBe('CustomException');
});

test('shortMessage truncates long messages', function () {
    $longMessage = str_repeat('A', 200);
    $data = new ExceptionData(
        class: 'Exception',
        message: $longMessage,
        trace: 'Trace'
    );

    $short = $data->shortMessage(50);

    expect($short)->toHaveLength(53); // 50 + '...'
    expect($short)->toEndWith('...');
});

test('shortMessage returns full message if under limit', function () {
    $data = new ExceptionData(
        class: 'Exception',
        message: 'Short message',
        trace: 'Trace'
    );

    expect($data->shortMessage(100))->toBe('Short message');
});
