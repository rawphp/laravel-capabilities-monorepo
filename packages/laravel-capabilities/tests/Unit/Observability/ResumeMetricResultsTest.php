<?php

// REQ-014: Approval resume/accept metric results (D-019). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Observability\InMemoryMetrics;
use Rawphp\Capabilities\Observability\InvokeTelemetry;

it('happy: approvals_resume_total result=executed_ok [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordResume('executed_ok');
    expect($m->get(InvokeTelemetry::METRIC_RESUME, ['result' => 'executed_ok']))->toBe(1);
});

it('happy: approvals_resume_total result=executed_failed [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordResume('executed_failed');
    expect($m->get(InvokeTelemetry::METRIC_RESUME, ['result' => 'executed_failed']))->toBe(1);
});

it('happy: approvals_resume_total result=skipped_lease [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordResume('skipped_lease');
    expect($m->get(InvokeTelemetry::METRIC_RESUME, ['result' => 'skipped_lease']))->toBe(1);
});

it('happy: approvals_resume_total result=stale [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordResume('stale');
    expect($m->get(InvokeTelemetry::METRIC_RESUME, ['result' => 'stale']))->toBe(1);
});

it('happy: approvals_accept_total result=executed_ok [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordAccept('executed_ok');
    expect($m->get(InvokeTelemetry::METRIC_ACCEPT, ['result' => 'executed_ok']))->toBe(1);
});

it('happy: approvals_accept_total result=executed_failed [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordAccept('executed_failed');
    expect($m->get(InvokeTelemetry::METRIC_ACCEPT, ['result' => 'executed_failed']))->toBe(1);
});

it('happy: approvals_accept_total result=in_progress [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordAccept('in_progress');
    expect($m->get(InvokeTelemetry::METRIC_ACCEPT, ['result' => 'in_progress']))->toBe(1);
});

it('happy: approvals_accept_total result=replay [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordAccept('replay');
    expect($m->get(InvokeTelemetry::METRIC_ACCEPT, ['result' => 'replay']))->toBe(1);
});

it('happy: approvals_accept_total result=rejected [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordAccept('rejected');
    expect($m->get(InvokeTelemetry::METRIC_ACCEPT, ['result' => 'rejected']))->toBe(1);
});

it('happy: approvals_accept_total result=forbidden [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordAccept('forbidden');
    expect($m->get(InvokeTelemetry::METRIC_ACCEPT, ['result' => 'forbidden']))->toBe(1);
});

it('happy: approvals_accept_total result=expired [D-019]', function () {
    $m = new InMemoryMetrics;
    (new InvokeTelemetry($m))->recordAccept('expired');
    expect($m->get(InvokeTelemetry::METRIC_ACCEPT, ['result' => 'expired']))->toBe(1);
});
