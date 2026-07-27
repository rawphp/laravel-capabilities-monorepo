<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Facades\Capability as CapabilityFacade;
use Rawphp\Capabilities\Pipeline\IdempotencyGuard;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use Illuminate\Support\Facades\Facade;
use DateTimeImmutable;

it("happy: after-run stage validate_output runs for caller agent on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['idempotency_key' => 'after-validate_output-agent']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('validate_output');
});

it("happy: after-run stage validate_output runs for caller mcp on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['idempotency_key' => 'after-validate_output-mcp']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('validate_output');
});

it("happy: after-run stage validate_output runs for caller http on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'after-validate_output-http']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('validate_output');
});

it("happy: after-run stage validate_output runs for caller cli on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['idempotency_key' => 'after-validate_output-cli']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('validate_output');
});

it("happy: after-run stage validate_output runs for caller job on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['idempotency_key' => 'after-validate_output-job']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('validate_output');
});

it("happy: after-run stage store_idempotency_result runs for caller agent on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['idempotency_key' => 'after-store_idempotency_result-agent']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('store_idempotency_result');
});

it("happy: after-run stage store_idempotency_result runs for caller mcp on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['idempotency_key' => 'after-store_idempotency_result-mcp']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('store_idempotency_result');
});

it("happy: after-run stage store_idempotency_result runs for caller http on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'after-store_idempotency_result-http']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('store_idempotency_result');
});

it("happy: after-run stage store_idempotency_result runs for caller cli on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['idempotency_key' => 'after-store_idempotency_result-cli']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('store_idempotency_result');
});

it("happy: after-run stage store_idempotency_result runs for caller job on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['idempotency_key' => 'after-store_idempotency_result-job']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('store_idempotency_result');
});

it("happy: after-run stage record_audit runs for caller agent on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['idempotency_key' => 'after-record_audit-agent']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('record_audit');
});

it("happy: after-run stage record_audit runs for caller mcp on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['idempotency_key' => 'after-record_audit-mcp']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('record_audit');
});

it("happy: after-run stage record_audit runs for caller http on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'after-record_audit-http']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('record_audit');
});

it("happy: after-run stage record_audit runs for caller cli on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['idempotency_key' => 'after-record_audit-cli']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('record_audit');
});

it("happy: after-run stage record_audit runs for caller job on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['idempotency_key' => 'after-record_audit-job']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('record_audit');
});

it("happy: after-run stage emit_events runs for caller agent on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['idempotency_key' => 'after-emit_events-agent']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('emit_events');
});

it("happy: after-run stage emit_events runs for caller mcp on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['idempotency_key' => 'after-emit_events-mcp']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('emit_events');
});

it("happy: after-run stage emit_events runs for caller http on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'after-emit_events-http']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('emit_events');
});

it("happy: after-run stage emit_events runs for caller cli on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['idempotency_key' => 'after-emit_events-cli']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('emit_events');
});

it("happy: after-run stage emit_events runs for caller job on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['idempotency_key' => 'after-emit_events-job']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('emit_events');
});

it("happy: after-run stage wire_response runs for caller agent on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent', ['idempotency_key' => 'after-wire_response-agent']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('wire_response');
});

it("happy: after-run stage wire_response runs for caller mcp on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp', ['idempotency_key' => 'after-wire_response-mcp']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('wire_response');
});

it("happy: after-run stage wire_response runs for caller http on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'after-wire_response-http']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('wire_response');
});

it("happy: after-run stage wire_response runs for caller cli on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli', ['idempotency_key' => 'after-wire_response-cli']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('wire_response');
});

it("happy: after-run stage wire_response runs for caller job on success [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job', ['idempotency_key' => 'after-wire_response-job']));
    expect($result->isOk())->toBeTrue()
        ->and($h['registry']->lastStages())->toContain('wire_response');
});

