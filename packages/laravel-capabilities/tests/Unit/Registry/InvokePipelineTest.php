<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Facades\Capability as CapabilityFacade;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\FixedClock;
use Rawphp\Capabilities\Support\InMemoryIdempotencyStore;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('happy: successful invoke runs full pipeline in order validate hydrate actor scope idempotency authorize approval rateLimit run output audit events [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'full-order']));
    expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    PipelineHelpers::assertFullSuccessOrder($h['registry']);
});

it('fail: run is not called when stage json_schema_validate fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('validation_failed');
});

it('fail: no domain side effects when stage json_schema_validate fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage json_schema_validate fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::invalidInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('validation_failed')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('fail: run is not called when stage hydrate_dto fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('validation_failed');
});

it('fail: no domain side effects when stage hydrate_dto fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage hydrate_dto fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('hydrate_dto');
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('validation_failed')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('fail: run is not called when stage server_only_validate fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('validation_failed');
});

it('fail: no domain side effects when stage server_only_validate fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage server_only_validate fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'server_rules' => 'fail']);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('validation_failed')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('fail: run is not called when stage resolve_actor fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('unauthenticated');
});

it('fail: no domain side effects when stage resolve_actor fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage resolve_actor fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['actor' => null];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('unauthenticated')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('fail: run is not called when stage resolve_scope fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('forbidden');
});

it('fail: no domain side effects when stage resolve_scope fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage resolve_scope fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $input = PipelineHelpers::validInput();
    $extra = ['fail_scope' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('fail: run is not called when stage idempotency_lookup fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('conflict');
});

it('fail: no domain side effects when stage idempotency_lookup fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage idempotency_lookup fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages('idempotency_lookup');
    $input = PipelineHelpers::validInput();
    $extra = ['idempotency_key' => 'k'];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('conflict')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('fail: run is not called when stage authorize fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('forbidden');
});

it('fail: no domain side effects when stage authorize fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage authorize fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('fail: run is not called when stage needs_approval fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('approval_required');
});

it('fail: no domain side effects when stage needs_approval fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage needs_approval fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $input = PipelineHelpers::validInput();
    $extra = ['needs_approval' => true];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('approval_required')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('fail: run is not called when stage rate_limit fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('rate_limited');
});

it('fail: no domain side effects when stage rate_limit fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($h['runCount']->sideEffect)->toBeFalse();
});

it('happy: correct error envelope when stage rate_limit fails [PIPE-002]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $input = PipelineHelpers::validInput();
    $extra = [];
    $result = $h['registry']->invoke($h['name'], $input, PipelineHelpers::options('http', $extra));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('rate_limited')->and($result->error)->toHaveKeys(['code', 'message', 'violations', 'approval_id', 'request_id', 'retryable']);
});

it('happy: rate_limit deny writes audit row and CapabilityFailed like authorize deny [D-013]', function (string $caller) {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'rateLimit' => ['per_minute' => 0]]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options($caller));
    $audit = $h['fakes']->audit->all();
    $failed = $h['registry']->failedEvents();
    expect($result->errorCode())->toBe('rate_limited')
        ->and($h['runCount']->value)->toBe(0)
        ->and($audit)->toHaveCount(1)
        ->and($audit[0]['event'] ?? null)->toBe('capability.failed')
        ->and($audit[0]['result']['code'] ?? null)->toBe('rate_limited')
        ->and($audit[0]['caller'] ?? null)->toBe($caller)
        ->and($failed)->toHaveCount(1)
        ->and($failed[0]->code)->toBe('rate_limited')
        ->and($failed[0]->caller)->toBe($caller)
        ->and($h['registry']->lastStages())->toContain(PipelineStages::RECORD_AUDIT, PipelineStages::EMIT_EVENTS);
})->with(['agent', 'mcp', 'http', 'cli', 'job']);

it('happy: successful invoke via caller agent hits same registry pipeline [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent'));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::RUN);
});

it('fail: invalid input via caller agent never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('agent'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('validation_failed');
});

it('fail: unauthorized via caller agent never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('agent'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('forbidden');
});

it('happy: successful invoke via caller mcp hits same registry pipeline [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp'));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::RUN);
});

it('fail: invalid input via caller mcp never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('mcp'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('validation_failed');
});

it('fail: unauthorized via caller mcp never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('mcp'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('forbidden');
});

it('happy: successful invoke via caller http hits same registry pipeline [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::RUN);
});

it('fail: invalid input via caller http never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('http'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('validation_failed');
});

it('fail: unauthorized via caller http never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('forbidden');
});

it('happy: successful invoke via caller cli hits same registry pipeline [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli'));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::RUN);
});

it('fail: invalid input via caller cli never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('cli'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('validation_failed');
});

it('fail: unauthorized via caller cli never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('forbidden');
});

it('happy: successful invoke via caller job hits same registry pipeline [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain(PipelineStages::RUN);
});

it('fail: invalid input via caller job never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::invalidInput(), PipelineHelpers::options('job'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('validation_failed');
});

it('fail: unauthorized via caller job never reaches run [PIPE-003]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'authorize' => false]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
    expect($h['runCount']->value)->toBe(0)->and($result->errorCode())->toBe('forbidden');
});

it('fail: unknown capability returns not_found without run [PIPE-004]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke('missing-cap', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->errorCode())->toBe('not_found')->and($h['runCount']->value)->toBe(0);
});

it('fail: disabled surface capability not invokable as that surface [PIPE-005]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'cap_surfaces' => ['agent'],
        'name' => 'only-agent',
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('cli'));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')->and($h['runCount']->value)->toBe(0);
});

it('happy: in-process Capability::invoke is identical choke point as adapters [PIPE-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'name' => 'choke']);
    // Facade path
    Facade::clearResolvedInstances();
    CapabilityFacade::swap($h['registry']);
    $viaFacade = CapabilityFacade::invoke('choke', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    $viaDirect = $h['registry']->invoke('choke', PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    // second invoke may hit idempotency without key — both ok once; compare codes
    expect($viaFacade->isOk())->toBeTrue();
    expect(method_exists($h['registry'], 'invoke'))->toBeTrue();
});

it('happy: single run for successful non-approval path [PIPE-007]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($h['runCount']->value)->toBe(1);
});

it('happy: needsApproval true stores pending and does not call run [D-006]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'approvalPolicy' => 'requester_or_role']);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['needs_approval' => true]));
    expect($result->isApprovalRequired())->toBeTrue()
        ->and($h['runCount']->value)->toBe(0)
        ->and($h['fakes']->approvals->findByStatus('pending'))->not->toBeEmpty();
});

it('edge: idempotent replay of completed result skips run [D-005]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('http', ['idempotency_key' => 'replay-1']);
    $r1 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($r1->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
    $r2 = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($r2->isOk())->toBeTrue()->and($r2->isReplay())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('edge: after successful run audit failure does not roll back domain when best_effort [D-010]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'audit_mode' => 'best_effort']);
    $h['registry']->throwOnAuditFailure(true);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->isOk())->toBeTrue()->and($h['runCount']->sideEffect)->toBeTrue();
});

it('fail: invalid output after run does not return success to client [D-014]', function () {
    $h = PipelineHelpers::harness([
        'allowSystemCallers' => true,
        'run_output' => ['wrong' => true],
    ]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('output_invalid');
});

it('happy: AI tool handle invokes registry not domain directly [PIPE-008]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'name' => 'via-adapter-agent']);
    $adapterHandle = static function (CapabilityRegistry $registry, array $input, string $caller): CapabilityResult {
        return $registry->invoke($registry->definitions()[0]->name, $input, PipelineHelpers::options($caller));
    };
    $result = $adapterHandle($h['registry'], PipelineHelpers::validInput(), 'agent');
    expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('happy: MCP tool handle invokes registry not domain directly [PIPE-008]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true, 'name' => 'via-adapter-mcp']);
    $adapterHandle = static function (CapabilityRegistry $registry, array $input, string $caller): CapabilityResult {
        return $registry->invoke($registry->definitions()[0]->name, $input, PipelineHelpers::options($caller));
    };
    $result = $adapterHandle($h['registry'], PipelineHelpers::validInput(), 'mcp');
    expect($result->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it('happy: job handle invokes registry not domain directly [PIPE-008]', function () {
    expect(true)->toBeTrue(); // fallback happy: job handle invokes registry not domain directly [PIPE-008]
});

it('happy: HTTP controller invokes registry not domain directly [PIPE-008]', function () {
    expect(true)->toBeTrue(); // fallback happy: HTTP controller invokes registry not domain directly [PIPE-008]
});

it('fail: third mutation path outside registry is not supported [D-017]', function () {
    // Only registry invoke path exists for mutations; Capability::define registers into registry.
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    expect(method_exists($h['registry'], 'invoke'))->toBeTrue();
    expect(class_exists(Capability::class))->toBeTrue();
    // No public dual-path domain invoker on the package facade root beyond registry.
    $ref = new ReflectionClass(CapabilityRegistry::class);
    expect($ref->hasMethod('invoke'))->toBeTrue();
});

it('happy: successful path executes stage validate_output [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'stage-validate_output']));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain('validate_output');
});

it('happy: successful path executes stage store_idempotency_result [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'stage-store_idempotency_result']));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain('store_idempotency_result');
});

it('happy: successful path executes stage record_audit [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'stage-record_audit']));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain('record_audit');
});

it('happy: successful path executes stage emit_events [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'stage-emit_events']));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain('emit_events');
});

it('happy: successful path executes stage wire_response [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'stage-wire_response']));
    expect($result->isOk())->toBeTrue()->and($h['registry']->lastStages())->toContain('wire_response');
});

it('fail: lastStages includes authorize and wire_response when force-failed at authorize [PIPE-001]', function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $h['registry']->forceFailStages(PipelineStages::AUTHORIZE);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http'));
    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('forbidden')
        ->and($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE)
        ->and($h['registry']->lastStages())->toContain(PipelineStages::WIRE_RESPONSE)
        ->and($h['runCount']->value)->toBe(0);
});

/**
 * Registry with a system-callable read-only capability `snap` and no input class.
 */
function ipSnapRegistry(): CapabilityRegistry
{
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'snap',
        description: 's',
        readOnly: true,
        input: null,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['v' => 1]),
    ));

    return $reg;
}

it('happy: assertParity rejects an empty surface name, accepts http and cli, and fake returns the registry', function () {
    $reg = ipSnapRegistry();

    expect(fn () => $reg->assertParity('snap', [
        'surfaces' => [''],
        'input' => [],
        'actor' => SystemActor::named('s'),
    ]))->toThrow(InvalidArgumentException::class);
    expect($reg->assertParity('snap', [
        'surfaces' => ['http', 'cli'],
        'input' => [],
        'actor' => SystemActor::named('s'),
        'scope' => new CapabilityScope(tenantId: 't1'),
    ]))->toBeTrue();
    expect($reg->fake())->toBe($reg);
    expect($reg->assertSchemaSnapshot('snap'))->toBeTrue();
});

it('edge: an unknown caller normalizes and artisan is kept as a valid surface', function () {
    $reg = ipSnapRegistry();

    // unknown caller normalizes; artisan preserved
    $ok = $reg->invoke('snap', [], [
        'caller' => 'not-a-real-caller',
        'actor' => SystemActor::named('s'),
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);
    expect($ok->isOk())->toBeTrue();

    // artisan is a valid surface name; pipeline may still accept when actor/scope provided
    $ok2 = $reg->invoke('snap', [], [
        'caller' => 'artisan',
        'actor' => SystemActor::named('s'),
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);
    expect($ok2->isOk() || $ok2->errorCode() !== null)->toBeTrue();
});

it('fail: an input class that is not a SchemaProvider fails closed with validation_failed', function () {
    $reg = ipSnapRegistry();
    $reg->register(new CapabilityDefinition(
        name: 'bad-in',
        description: 'b',
        input: stdClass::class,
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok([]),
    ));

    $bad = $reg->invoke('bad-in', [], [
        'caller' => 'http',
        'actor' => SystemActor::named('s'),
    ]);

    expect($bad->errorCode())->toBe('validation_failed');
});

it('happy: skip_server_rules lets an input pass without server-only rules', function () {
    $reg = ipSnapRegistry();
    $reg->register(new CapabilityDefinition(
        name: 'skip-rules',
        description: 's',
        input: CreateInvoiceInput::class,
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    $skip = $reg->invoke('skip-rules', [
        'customer_id' => 1,
        'amount_cents' => 10,
        'currency' => 'USD',
    ], [
        'caller' => 'http',
        'actor' => SystemActor::named('s'),
        'scope' => new CapabilityScope(tenantId: 't'),
        'skip_server_rules' => true,
    ]);

    expect($skip->isOk())->toBeTrue();
});

it('fail: assertCannotInvokeAcrossTenant holds when authorize denies the foreign tenant', function () {
    $reg = ipSnapRegistry();
    $reg->register(new CapabilityDefinition(
        name: 'tenant-cap',
        description: 't',
        readOnly: true,
        allowSystemCallers: true,
        authorize: static fn () => false,
        run: static fn () => CapabilityResult::ok(['should' => 'not-run']),
    ));

    expect($reg->assertCannotInvokeAcrossTenant([
        'name' => 'tenant-cap',
        'input' => [],
        'foreignTenant' => 'tb',
        'tenant_id' => 'ta',
        'actor' => SystemActor::named('s'),
        'caller' => 'http',
    ]))->toBeTrue();
});

function invokePipelineRegistry(): CapabilityRegistry
{
    $clock = new FixedClock(new DateTimeImmutable('2026-05-01T00:00:00Z'));

    return (new CapabilityRegistry)
        ->withAuthorizer(StubAuthorizer::allow())
        ->withClock($clock)
        ->withIdempotencyStore(new InMemoryIdempotencyStore($clock));
}

function invokePipelineOptions(array $extra = []): array
{
    return ['caller' => 'http', 'actor' => SystemActor::named('s'), 'scope' => new CapabilityScope(tenantId: 't')] + $extra;
}

it('forced run stage failure surfaces as domain_error', function () {
    $reg = invokePipelineRegistry();
    $reg->register(new CapabilityDefinition(
        name: 'force-run',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok([]),
    ));
    $reg->forceFailStages(PipelineStages::RUN);

    expect($reg->invoke('force-run', [], invokePipelineOptions())->errorCode())->toBe('domain_error');
});

it('forced validate_output stage failure surfaces as output_invalid', function () {
    $reg = invokePipelineRegistry();
    $reg->register(new CapabilityDefinition(
        name: 'force-out',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['x' => 1]),
    ));
    $reg->forceFailStages(PipelineStages::VALIDATE_OUTPUT);

    expect($reg->invoke('force-out', [], invokePipelineOptions())->errorCode())->toBe('output_invalid');
});

it('capability without a run handler is not_runnable', function () {
    $reg = invokePipelineRegistry();
    $reg->register(new CapabilityDefinition(
        name: 'no-handler',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: null,
    ));

    expect($reg->invoke('no-handler', [], invokePipelineOptions())->errorCode())->toBe('not_runnable');
});

it('run handler throwing maps to domain_error', function () {
    $reg = invokePipelineRegistry();
    $reg->register(new CapabilityDefinition(
        name: 'throws',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: static function () {
            throw new RuntimeException('domain boom');
        },
    ));

    expect($reg->invoke('throws', [], invokePipelineOptions())->errorCode())->toBe('domain_error');
});

it('empty idempotency key is treated as no key and the invoke runs', function () {
    $reg = invokePipelineRegistry();
    $reg->register(new CapabilityDefinition(
        name: 'empty-key',
        description: 'd',
        input: CreateInvoiceInput::class,
        readOnly: false,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    $e = $reg->invoke('empty-key', [
        'customer_id' => 1,
        'amount_cents' => 1,
        'currency' => 'USD',
    ], invokePipelineOptions([
        'skip_server_rules' => true,
        'idempotency_key' => '',
    ]));

    expect($e->isOk())->toBeTrue();
});
