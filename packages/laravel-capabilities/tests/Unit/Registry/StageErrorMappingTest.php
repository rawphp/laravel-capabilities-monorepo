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

it("happy: stage json_schema_validate maps to validation_failed for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage json_schema_validate does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage json_schema_validate maps to validation_failed for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage json_schema_validate does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage json_schema_validate maps to validation_failed for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage json_schema_validate does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage json_schema_validate maps to validation_failed for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage json_schema_validate does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage json_schema_validate maps to validation_failed for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage json_schema_validate does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage hydrate_dto maps to validation_failed for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage hydrate_dto does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage hydrate_dto maps to validation_failed for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage hydrate_dto does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage hydrate_dto maps to validation_failed for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage hydrate_dto does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage hydrate_dto maps to validation_failed for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage hydrate_dto does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage hydrate_dto maps to validation_failed for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage hydrate_dto does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage server_only_validate maps to validation_failed for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage server_only_validate does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage server_only_validate maps to validation_failed for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage server_only_validate does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage server_only_validate maps to validation_failed for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage server_only_validate does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage server_only_validate maps to validation_failed for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage server_only_validate does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage server_only_validate maps to validation_failed for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('validation_failed');
});

it("fail: stage server_only_validate does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_actor maps to unauthenticated for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('unauthenticated');
});

it("fail: stage resolve_actor does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_actor maps to unauthenticated for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('unauthenticated');
});

it("fail: stage resolve_actor does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_actor maps to unauthenticated for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('unauthenticated');
});

it("fail: stage resolve_actor does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_actor maps to unauthenticated for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('unauthenticated');
});

it("fail: stage resolve_actor does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_actor maps to unauthenticated for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('unauthenticated');
});

it("fail: stage resolve_actor does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_scope maps to forbidden for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage resolve_scope does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_scope maps to forbidden for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage resolve_scope does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_scope maps to forbidden for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage resolve_scope does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_scope maps to forbidden for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage resolve_scope does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage resolve_scope maps to forbidden for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage resolve_scope does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage idempotency_lookup maps to conflict for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('conflict');
});

it("fail: stage idempotency_lookup does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage idempotency_lookup maps to conflict for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('conflict');
});

it("fail: stage idempotency_lookup does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage idempotency_lookup maps to conflict for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('conflict');
});

it("fail: stage idempotency_lookup does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage idempotency_lookup maps to conflict for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('conflict');
});

it("fail: stage idempotency_lookup does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage idempotency_lookup maps to conflict for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('conflict');
});

it("fail: stage idempotency_lookup does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage authorize maps to forbidden for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage authorize does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage authorize maps to forbidden for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage authorize does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage authorize maps to forbidden for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage authorize does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage authorize maps to forbidden for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage authorize does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage authorize maps to forbidden for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('forbidden');
});

it("fail: stage authorize does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage needs_approval maps to approval_required for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('approval_required');
});

it("fail: stage needs_approval does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage needs_approval maps to approval_required for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('approval_required');
});

it("fail: stage needs_approval does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage needs_approval maps to approval_required for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('approval_required');
});

it("fail: stage needs_approval does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage needs_approval maps to approval_required for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('approval_required');
});

it("fail: stage needs_approval does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage needs_approval maps to approval_required for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('approval_required');
});

it("fail: stage needs_approval does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage rate_limit maps to rate_limited for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->errorCode())->toBe('rate_limited');
});

it("fail: stage rate_limit does not call run for agent [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage rate_limit maps to rate_limited for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->errorCode())->toBe('rate_limited');
});

it("fail: stage rate_limit does not call run for mcp [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage rate_limit maps to rate_limited for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->errorCode())->toBe('rate_limited');
});

it("fail: stage rate_limit does not call run for http [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage rate_limit maps to rate_limited for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->errorCode())->toBe('rate_limited');
});

it("fail: stage rate_limit does not call run for cli [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

it("happy: stage rate_limit maps to rate_limited for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->errorCode())->toBe('rate_limited');
});

it("fail: stage rate_limit does not call run for job [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->isOk())->toBeFalse();
});

