<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('edge: needsApproval can branch on caller agent [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'agent',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it('fail: needsApproval branch for agent uses derived caller not header [D-022]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'agent',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
    $r2 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'http',
    ]));
    if ('agent' !== 'http') {
        expect($r2->isApprovalRequired())->toBeFalse();
    } else {
        expect($r2->isApprovalRequired())->toBeTrue();
    }
});

it('edge: needsApproval can branch on caller mcp [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', [
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'mcp',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it('fail: needsApproval branch for mcp uses derived caller not header [D-022]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'mcp',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
    $r2 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'http',
    ]));
    if ('mcp' !== 'http') {
        expect($r2->isApprovalRequired())->toBeFalse();
    } else {
        expect($r2->isApprovalRequired())->toBeTrue();
    }
});

it('edge: needsApproval can branch on caller http [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', [
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'http',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it('fail: needsApproval branch for http uses derived caller not header [D-022]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'http',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
    $r2 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'http',
    ]));
    if ('http' !== 'http') {
        expect($r2->isApprovalRequired())->toBeFalse();
    } else {
        expect($r2->isApprovalRequired())->toBeTrue();
    }
});

it('edge: needsApproval can branch on caller cli [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', [
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'cli',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it('fail: needsApproval branch for cli uses derived caller not header [D-022]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'cli',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
    $r2 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'http',
    ]));
    if ('cli' !== 'http') {
        expect($r2->isApprovalRequired())->toBeFalse();
    } else {
        expect($r2->isApprovalRequired())->toBeTrue();
    }
});

it('edge: needsApproval can branch on caller job [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', [
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'job',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
});

it('fail: needsApproval branch for job uses derived caller not header [D-022]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'job',
    ]));
    expect($r->isApprovalRequired())->toBeTrue();
    $r2 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', [
        'headers' => ['X-Capability-Caller' => 'http'],
        'needs_approval_callback' => fn ($in, $ctx) => $ctx->caller() === 'http',
    ]));
    if ('job' !== 'http') {
        expect($r2->isApprovalRequired())->toBeFalse();
    } else {
        expect($r2->isApprovalRequired())->toBeTrue();
    }
});

it('happy: example large amount requires approval for agent mcp cli not necessarily http [D-006]', function () {
    foreach (['agent', 'mcp', 'cli'] as $caller) {
        $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $r = $h['registry']->invoke($h['name'], ['customer_id' => 1, 'amount_cents' => 100000, 'currency' => 'USD'], PipelineHelpers::options($caller, [
            'needs_approval_callback' => fn ($in, $ctx) => in_array($ctx->caller(), ['agent', 'mcp', 'cli'], true),
        ]));
        expect($r->isApprovalRequired())->toBeTrue();
    }
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $r = $h['registry']->invoke($h['name'], ['customer_id' => 1, 'amount_cents' => 100000, 'currency' => 'USD'], PipelineHelpers::options('http', [
        'needs_approval_callback' => fn ($in, $ctx) => in_array($ctx->caller(), ['agent', 'mcp', 'cli'], true),
    ]));
    expect($r->isApprovalRequired())->toBeFalse();
});
