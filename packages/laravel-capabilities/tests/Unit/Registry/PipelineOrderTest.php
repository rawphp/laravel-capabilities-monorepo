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

it("happy: pipeline position 00 is json_schema_validate [PIPE-001]", function () {
    expect(PipelineStages::ordered()[0])->toBe(PipelineStages::JSON_SCHEMA_VALIDATE);
});

it("happy: pipeline position 01 is hydrate_dto [PIPE-001]", function () {
    expect(PipelineStages::ordered()[1])->toBe(PipelineStages::HYDRATE_DTO);
});

it("edge: stage hydrate_dto runs after json_schema_validate [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-1']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('json_schema_validate', $stages, true);
    $b = array_search('hydrate_dto', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 02 is server_only_validate [PIPE-001]", function () {
    expect(PipelineStages::ordered()[2])->toBe(PipelineStages::SERVER_ONLY_VALIDATE);
});

it("edge: stage server_only_validate runs after hydrate_dto [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-2']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('hydrate_dto', $stages, true);
    $b = array_search('server_only_validate', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 03 is resolve_actor [PIPE-001]", function () {
    expect(PipelineStages::ordered()[3])->toBe(PipelineStages::RESOLVE_ACTOR);
});

it("edge: stage resolve_actor runs after server_only_validate [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-3']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('server_only_validate', $stages, true);
    $b = array_search('resolve_actor', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 04 is resolve_scope [PIPE-001]", function () {
    expect(PipelineStages::ordered()[4])->toBe(PipelineStages::RESOLVE_SCOPE);
});

it("edge: stage resolve_scope runs after resolve_actor [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-4']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('resolve_actor', $stages, true);
    $b = array_search('resolve_scope', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 05 is idempotency_lookup [PIPE-001]", function () {
    expect(PipelineStages::ordered()[5])->toBe(PipelineStages::IDEMPOTENCY_LOOKUP);
});

it("edge: stage idempotency_lookup runs after resolve_scope [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-5']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('resolve_scope', $stages, true);
    $b = array_search('idempotency_lookup', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 06 is authorize [PIPE-001]", function () {
    expect(PipelineStages::ordered()[6])->toBe(PipelineStages::AUTHORIZE);
});

it("edge: stage authorize runs after idempotency_lookup [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-6']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('idempotency_lookup', $stages, true);
    $b = array_search('authorize', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 07 is needs_approval [PIPE-001]", function () {
    expect(PipelineStages::ordered()[7])->toBe(PipelineStages::NEEDS_APPROVAL);
});

it("edge: stage needs_approval runs after authorize [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-7']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('authorize', $stages, true);
    $b = array_search('needs_approval', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 08 is rate_limit [PIPE-001]", function () {
    expect(PipelineStages::ordered()[8])->toBe(PipelineStages::RATE_LIMIT);
});

it("edge: stage rate_limit runs after needs_approval [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-8']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('needs_approval', $stages, true);
    $b = array_search('rate_limit', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 09 is run [PIPE-001]", function () {
    expect(PipelineStages::ordered()[9])->toBe(PipelineStages::RUN);
});

it("edge: stage run runs after rate_limit [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-9']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('rate_limit', $stages, true);
    $b = array_search('run', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 10 is validate_output [PIPE-001]", function () {
    expect(PipelineStages::ordered()[10])->toBe(PipelineStages::VALIDATE_OUTPUT);
});

it("edge: stage validate_output runs after run [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-10']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('run', $stages, true);
    $b = array_search('validate_output', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 11 is store_idempotency [PIPE-001]", function () {
    expect(PipelineStages::ordered()[11])->toBe(PipelineStages::STORE_IDEMPOTENCY);
});

it("edge: stage store_idempotency runs after validate_output [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-11']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('validate_output', $stages, true);
    $b = array_search('store_idempotency', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 12 is record_audit [PIPE-001]", function () {
    expect(PipelineStages::ordered()[12])->toBe(PipelineStages::RECORD_AUDIT);
});

it("edge: stage record_audit runs after store_idempotency [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-12']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('store_idempotency', $stages, true);
    $b = array_search('record_audit', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 13 is emit_events [PIPE-001]", function () {
    expect(PipelineStages::ordered()[13])->toBe(PipelineStages::EMIT_EVENTS);
});

it("edge: stage emit_events runs after record_audit [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-13']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('record_audit', $stages, true);
    $b = array_search('emit_events', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

it("happy: pipeline position 14 is wire_response [PIPE-001]", function () {
    expect(PipelineStages::ordered()[14])->toBe(PipelineStages::WIRE_RESPONSE);
});

it("edge: stage wire_response runs after emit_events [PIPE-001]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('http', ['idempotency_key' => 'ord-14']));
    expect($result->isOk())->toBeTrue();
    $stages = $h['registry']->lastStages();
    $a = array_search('emit_events', $stages, true);
    $b = array_search('wire_response', $stages, true);
    expect($a)->not->toBeFalse()->and($b)->not->toBeFalse()->and($b)->toBeGreaterThan($a);
});

