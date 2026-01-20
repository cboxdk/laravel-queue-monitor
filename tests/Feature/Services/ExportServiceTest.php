<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\DataTransferObjects\JobFilterData;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Services\ExportService;

test('exports jobs to CSV format', function () {
    JobMonitor::factory()->count(3)->create();

    $service = app(ExportService::class);
    $filters = new JobFilterData(limit: 100);

    $csv = $service->toCsv($filters);

    expect($csv)->toContain('UUID,Job Class,Queue');
    expect(substr_count($csv, "\n"))->toBe(4); // Header + 3 jobs
});

test('exports jobs to JSON format', function () {
    JobMonitor::factory()->count(2)->create();

    $service = app(ExportService::class);
    $filters = new JobFilterData(limit: 100);

    $json = $service->toJson($filters);

    expect($json)->toBeArray();
    expect($json)->toHaveCount(2);
    expect($json[0])->toHaveKeys(['uuid', 'job_class', 'status', 'metrics']);
});

test('statistics report includes all sections', function () {
    JobMonitor::factory()->count(5)->create();

    $service = app(ExportService::class);
    $report = $service->statisticsReport();

    expect($report)->toHaveKeys(['generated_at', 'global', 'servers', 'queue_health']);
});

test('failed jobs report groups by exception', function () {
    JobMonitor::factory()->failed()->create(['exception_class' => 'RuntimeException']);
    JobMonitor::factory()->failed()->create(['exception_class' => 'RuntimeException']);
    JobMonitor::factory()->failed()->create(['exception_class' => 'LogicException']);

    $service = app(ExportService::class);
    $report = $service->failedJobsReport();

    expect($report['total_failed'])->toBe(3);
    expect($report['by_exception'])->toHaveKey('RuntimeException');
    expect($report['by_exception']['RuntimeException']['count'])->toBe(2);
});
