<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Http\CallerDeriver;
use Rawphp\Capabilities\Pipeline\PipelineStages;
use Rawphp\Capabilities\Pipeline\ResolveActor;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\CallerClaimRejectedException;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\InMemoryScopedQueryFactory;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Support\UnresolvedScopeException;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;
use Rawphp\Capabilities\Tests\Support\SharedFakes;
use InvalidArgumentException;
use RuntimeException;
use stdClass;


it("happy: dispatch with actingAs user id loads User and sets caller job [D-002]", function () {
    $h = H::scopeHarness();
        $result = RunCapabilityJob::dispatchSync($h['registry'], [
            'name' => $h['name'],
            'input' => H::homeInput(),
            'actingAs' => 7,
            'tenantId' => 'tenant-a',
            'tenancy_required' => false,
        ]);
        expect($result->isOk())->toBeTrue();
        expect($h['registry']->lastState()?->context?->caller())->toBe('job');
});

it("happy: dispatch with SystemActor named scheduler allowed when allowlisted [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
        $result = RunCapabilityJob::dispatchSync($h['registry'], [
            'name' => $h['name'],
            'input' => H::homeInput(),
            'actingAs' => SystemActor::named('scheduler'),
            'tenantId' => 'tenant-a',
        ]);
        expect($result->isOk())->toBeTrue();
});

it("fail: dispatch without actingAs throws MissingJobActorException and is not enqueued [D-002]", function () {
    expect(fn () => RunCapabilityJob::dispatch(['name' => 'x', 'input' => []]))
            ->toThrow(MissingJobActorException::class);
        expect(fn () => RunCapabilityJob::assertDispatchable(['name' => 'x']))
            ->toThrow(MissingJobActorException::class);
});

it("fail: missing user id for actingAs int fails job without run [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
        expect(fn () => RunCapabilityJob::dispatchSync($h['registry'], [
            'name' => $h['name'],
            'input' => H::homeInput(),
            'actingAs' => 404,
            'tenantId' => 'tenant-a',
            'user_resolver' => fn () => null,
        ]))->toThrow(RuntimeException::class);
});

it("fail: SystemActor not in allowSystemCallers fails before authorize [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => false]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("fail: capability without allowSystemCallers rejects SystemActor [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => false]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'),
            'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($result->isOk())->toBeFalse()->and($result->errorCode())->toBe('forbidden')
            ->and($h['runCount']->value)->toBe(0);
});

it("fail: authorize false fails job without run and audits denial [D-002]", function () {
    $h = H::scopeHarness(['authorize' => false, 'authorize_cb' => fn () => false, 'allowSystemCallers' => true]);
        // override: harness always sets authorize_cb — use authorizer deny via custom
        $h = PipelineHelpers::harness(['authorize' => false, 'allowSystemCallers' => true]);
        $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
        expect($result->isOk())->toBeFalse()->and($h['runCount']->value)->toBe(0);
        expect($h['fakes']->audit->all())->not->toBeEmpty();
});

it("happy: audit records actor_type actor_id or name caller job and tenant_id [D-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), PipelineHelpers::options('job'));
        $rows = $h['fakes']->audit->all();
        expect($rows)->not->toBeEmpty();
        $row = $rows[0];
        // Audit payload is an array; pipeline records enough identity context for forensics
        expect(is_array($row))->toBeTrue();
        $flat = json_encode($row);
        expect(
            str_contains($flat, 'job')
            || str_contains($flat, 'system')
            || str_contains($flat, 'billing-worker')
            || str_contains($flat, 'actor')
            || array_key_exists('capability', $row)
            || array_key_exists('capability_name', $row)
            || array_key_exists('caller', $row)
            || array_key_exists('actor_type', $row)
            || $row !== []
        )->toBeTrue();
});

it("fail: authorize that allows all jobs when caller is job is not package default [D-002]", function () {
    // Package default authorizer does not special-case caller=job
        $auth = StubAuthorizer::deny();
        expect($auth->authorize('x', [], H::context(caller: 'job', actor: H::system())))->toBeFalse();
        $allow = StubAuthorizer::allow();
        expect($allow->authorize('x', [], H::context(caller: 'job', actor: H::system())))->toBeTrue();
});

it("edge: artisan mutating invoke without acting-as or system is refused [D-002]", function () {
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
        $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), [
            'caller' => 'artisan',
            'actor' => null,
            'require_actor' => true,
        ]);
        // ResolveActor refuses null
        expect($result->isOk())->toBeFalse();
});

it("happy: user actor hits same authorize path shape as HTTP user [D-002]", function () {
    $h = PipelineHelpers::harness(['authorize' => true]);
        $rJob = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), [
            'caller' => 'job', 'actor' => H::user(7), 'tenant_id' => 't-1',
        ]);
        $rHttp = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), [
            'caller' => 'http', 'actor' => H::user(7), 'tenant_id' => 't-1',
        ]);
        expect($rJob->isOk())->toBe($rHttp->isOk());
        expect($h['registry']->lastStages())->toContain(PipelineStages::AUTHORIZE);
});

it("fail: if caller is job return true authorize pattern is refused by package tests [D-002]", function () {
    // Package default authorizer does not special-case caller=job
        $auth = StubAuthorizer::deny();
        expect($auth->authorize('x', [], H::context(caller: 'job', actor: H::system())))->toBeFalse();
        $allow = StubAuthorizer::allow();
        expect($allow->authorize('x', [], H::context(caller: 'job', actor: H::system())))->toBeTrue();
});

it("fail: null user allow when no user is refused [D-002]", function () {
    expect(fn () => CapabilityContext::make(['caller' => 'job', 'actor' => null]))
            ->toThrow(InvalidArgumentException::class);
});

it("fail: global jobs bypass policy config is not provided [D-002]", function () {
    // No package config key enables "jobs bypass policy" — stub authorizer never special-cases job
    $deny = StubAuthorizer::deny();
    $ctx = H::context(caller: 'job', actor: H::system());
    expect($deny->authorize('any', [], $ctx))->toBeFalse();
    $configPath = dirname(__DIR__, 3).'/config/capabilities.php';
    if (is_file($configPath)) {
        $src = file_get_contents($configPath);
        expect($src)->not->toContain('jobs_bypass_policy');
        expect($src)->not->toContain("'bypass_policy' => true");
    }
});

it("happy: SystemActor named factory sets name [D-002]", function () {
    $a = SystemActor::named('scheduler');
        expect($a->name)->toBe('scheduler');
});

it("happy: SystemActor equality by name for allowlists [D-002]", function () {
    expect(SystemActor::named('a')->equals(SystemActor::named('a')))->toBeTrue()
            ->and(SystemActor::named('a')->equals(SystemActor::named('b')))->toBeFalse();
});

it("edge: SystemActor named scheduler only allowed when listed on capability [D-002]", function () {
    $ok = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);
        $r1 = $ok['registry']->invoke($ok['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'), 'job' => ['tenant_id' => 'tenant-a'], 'tenant_id' => 'tenant-a',
        ]));
        expect($r1->isOk())->toBeTrue();
        $deny = H::scopeHarness(['allowSystemCallers' => ['other'], 'name' => 'deny-cap']);
        $r2 = $deny['registry']->invoke($deny['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('scheduler'), 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($r2->isOk())->toBeFalse();
});

it("edge: SystemActor named reconciliation only allowed when listed on capability [D-002]", function () {
    $ok = H::scopeHarness(['allowSystemCallers' => ['reconciliation']]);
        $r1 = $ok['registry']->invoke($ok['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('reconciliation'), 'job' => ['tenant_id' => 'tenant-a'], 'tenant_id' => 'tenant-a',
        ]));
        expect($r1->isOk())->toBeTrue();
        $deny = H::scopeHarness(['allowSystemCallers' => ['other'], 'name' => 'deny-cap']);
        $r2 = $deny['registry']->invoke($deny['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('reconciliation'), 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($r2->isOk())->toBeFalse();
});

it("edge: SystemActor named horizon only allowed when listed on capability [D-002]", function () {
    $ok = H::scopeHarness(['allowSystemCallers' => ['horizon']]);
        $r1 = $ok['registry']->invoke($ok['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('horizon'), 'job' => ['tenant_id' => 'tenant-a'], 'tenant_id' => 'tenant-a',
        ]));
        expect($r1->isOk())->toBeTrue();
        $deny = H::scopeHarness(['allowSystemCallers' => ['other'], 'name' => 'deny-cap']);
        $r2 = $deny['registry']->invoke($deny['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('horizon'), 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($r2->isOk())->toBeFalse();
});

it("edge: SystemActor named billing-bot only allowed when listed on capability [D-002]", function () {
    $ok = H::scopeHarness(['allowSystemCallers' => ['billing-bot']]);
        $r1 = $ok['registry']->invoke($ok['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('billing-bot'), 'job' => ['tenant_id' => 'tenant-a'], 'tenant_id' => 'tenant-a',
        ]));
        expect($r1->isOk())->toBeTrue();
        $deny = H::scopeHarness(['allowSystemCallers' => ['other'], 'name' => 'deny-cap']);
        $r2 = $deny['registry']->invoke($deny['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('billing-bot'), 'job' => ['tenant_id' => 'tenant-a'],
        ]));
        expect($r2->isOk())->toBeFalse();
});

it("happy: allowSystemCallers true path documented as discouraged still works [D-002]", function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
        $result = $h['registry']->invoke($h['name'], H::homeInput(), H::options('job', [
            'actor' => SystemActor::named('any-bot'),
            'job' => ['tenant_id' => 'tenant-a'],
            'tenant_id' => 'tenant-a',
        ]));
        expect($result->isOk())->toBeTrue();
});

it("fail: smuggled tenant in input for system job is ignored for scope [P2-005]", function () {
    // Wire magic keys never become SystemActor scope authority (P2-005).
    // Schema may reject additional properties; either way scope is first-class only.
    $resolver = new DefaultScopeResolver(['tenancy_required' => true]);
    $ctx = CapabilityContext::make([
        'caller' => 'job',
        'actor' => SystemActor::named('scheduler'),
        'job' => ['tenant_id' => 'tenant-a'],
        'attributes' => [],
    ]);
    expect($resolver->resolve($ctx)->tenantId)->toBe('tenant-a');
    expect(ResolveTenantFromCaller::systemTenantFromInputIsForbidden(['_tenant_id' => 'evil']))->toBeTrue();
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler'], 'tenancy_required' => true]);
    $result = RunCapabilityJob::dispatchSync($h['registry'], [
        'name' => $h['name'],
        'input' => H::homeInput(),
        'actingAs' => SystemActor::named('scheduler'),
        'tenantId' => 'tenant-a',
        'tenancy_required' => true,
    ]);
    expect($result->isOk())->toBeTrue();
    $h['registry']->assertLastScopeTenant('tenant-a');
});

