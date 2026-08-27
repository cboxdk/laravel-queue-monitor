<?php

declare(strict_types=1);

use Cbox\LaravelQueueMonitor\Enums\JobStatus;
use Cbox\LaravelQueueMonitor\Models\JobMonitor;
use Cbox\LaravelQueueMonitor\Testing\InteractsWithQueueMonitor;

uses(InteractsWithQueueMonitor::class);

it('asserts a job was recorded, and how many times', function () {
    JobMonitor::factory()->count(2)->create(['job_class' => 'App\\Jobs\\SendInvoice']);

    $this->assertJobRecorded('App\\Jobs\\SendInvoice');
    $this->assertJobRecorded('App\\Jobs\\SendInvoice', 2);
    $this->assertJobNotRecorded('App\\Jobs\\Other');
});

it('asserts nothing was recorded on a clean slate', function () {
    $this->assertNothingRecorded();
});

it('asserts a job was recorded with a given status', function () {
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\Charge', 'status' => JobStatus::COMPLETED]);

    $this->assertJobRecordedWithStatus('App\\Jobs\\Charge', JobStatus::COMPLETED);
    $this->assertJobRecordedWithStatus('App\\Jobs\\Charge', 'completed');
    $this->assertJobCompleted('App\\Jobs\\Charge');
});

it('asserts a failed or timed-out job', function () {
    JobMonitor::factory()->failed()->create(['job_class' => 'App\\Jobs\\Flaky']);

    $this->assertJobFailed('App\\Jobs\\Flaky');
});

it('exposes the recorded jobs as a collection', function () {
    JobMonitor::factory()->count(3)->create(['job_class' => 'App\\Jobs\\Report']);
    JobMonitor::factory()->create(['job_class' => 'App\\Jobs\\Other']);

    expect($this->recordedJobs('App\\Jobs\\Report'))->toHaveCount(3);
    expect($this->recordedJobs())->toHaveCount(4);
});
