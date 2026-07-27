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

it("fail: stage json_schema_validate fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage json_schema_validate fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage json_schema_validate fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage json_schema_validate fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage json_schema_validate fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage json_schema_validate fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage json_schema_validate fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage json_schema_validate fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage json_schema_validate fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage json_schema_validate fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage json_schema_validate fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage json_schema_validate fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage json_schema_validate fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage json_schema_validate fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage json_schema_validate fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage hydrate_dto fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage hydrate_dto fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage hydrate_dto fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage hydrate_dto fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage hydrate_dto fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage hydrate_dto fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage hydrate_dto fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage hydrate_dto fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage hydrate_dto fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage hydrate_dto fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage hydrate_dto fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage hydrate_dto fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage hydrate_dto fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage hydrate_dto fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage hydrate_dto fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage server_only_validate fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage server_only_validate fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage server_only_validate fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage server_only_validate fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage server_only_validate fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage server_only_validate fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage server_only_validate fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage server_only_validate fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage server_only_validate fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage server_only_validate fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage server_only_validate fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage server_only_validate fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage server_only_validate fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage server_only_validate fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage server_only_validate fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('validation_failed')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_actor fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_actor fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_actor fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('unauthenticated')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_actor fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_actor fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_actor fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('unauthenticated')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_actor fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_actor fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_actor fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('unauthenticated')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_actor fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_actor fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_actor fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('unauthenticated')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_actor fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_actor fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_actor fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('unauthenticated')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_scope fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_scope fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_scope fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_scope fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_scope fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_scope fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_scope fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_scope fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_scope fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_scope fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_scope fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_scope fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage resolve_scope fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage resolve_scope fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage resolve_scope fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage idempotency_lookup fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage idempotency_lookup fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage idempotency_lookup fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('conflict')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage idempotency_lookup fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage idempotency_lookup fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage idempotency_lookup fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('conflict')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage idempotency_lookup fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage idempotency_lookup fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage idempotency_lookup fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('conflict')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage idempotency_lookup fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage idempotency_lookup fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage idempotency_lookup fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('conflict')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage idempotency_lookup fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage idempotency_lookup fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage idempotency_lookup fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('conflict')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage authorize fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage authorize fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage authorize fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage authorize fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage authorize fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage authorize fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage authorize fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage authorize fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage authorize fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage authorize fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage authorize fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage authorize fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage authorize fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage authorize fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage authorize fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage needs_approval fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage needs_approval fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage needs_approval fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('approval_required')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage needs_approval fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage needs_approval fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage needs_approval fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('approval_required')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage needs_approval fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage needs_approval fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage needs_approval fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('approval_required')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage needs_approval fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage needs_approval fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage needs_approval fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('approval_required')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage needs_approval fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage needs_approval fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage needs_approval fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('approval_required')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage rate_limit fail on caller agent does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage rate_limit fail on caller agent has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage rate_limit fail on caller agent returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('agent', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('rate_limited')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage rate_limit fail on caller mcp does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage rate_limit fail on caller mcp has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage rate_limit fail on caller mcp returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('mcp', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('rate_limited')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage rate_limit fail on caller http does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage rate_limit fail on caller http has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage rate_limit fail on caller http returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('rate_limited')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage rate_limit fail on caller cli does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage rate_limit fail on caller cli has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage rate_limit fail on caller cli returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('cli', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('rate_limited')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it("fail: stage rate_limit fail on caller job does not call run [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->value)->toBe(0);
});

it("fail: stage rate_limit fail on caller job has no domain side effects [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it("happy: stage rate_limit fail on caller job returns structured error [PIPE-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('job', $extra));
    expect($result->isOk())->toBeFalse()
        ->and($result->error)->toBeArray()
        ->and($result->errorCode())->toBe('rate_limited')
        ->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

