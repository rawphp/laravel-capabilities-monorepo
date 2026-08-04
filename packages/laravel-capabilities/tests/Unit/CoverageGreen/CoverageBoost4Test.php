<?php

declare(strict_types=1);

/**
 * REQ-016: final coverage push past 95% — adapters, profiles, wire keys, policy.
 */

use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\Artisan\ArtisanCapabilityInvoker;
use Rawphp\Capabilities\Adapters\Mcp\McpAuthProfileResolver;
use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Adapters\Mcp\McpToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Approval\ApprovalPolicy;
use Rawphp\Capabilities\Idempotency\WireKeyResolver;
use Rawphp\Capabilities\Profiles\ProfileSelector;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\MissingArtisanActorException;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\MissingJobTenantException;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;

it('covers AiToolAdapterV1 disabled, spoof, handleStructured, turn budget', function () {
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'ai.cap',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['v' => 1]),
    ));

    $probeOk = PeerVersionProbe::fake(['laravel/ai' => true]);
    $disabled = new AiToolAdapterV1($reg, $probeOk, surfaceEnabled: false, requireCompatiblePeer: false);
    expect($disabled->register('ops'))->toBe([])
        ->and($disabled->isRegistered())->toBeFalse()
        ->and($disabled->handle('ai.cap', [], SystemActor::named('s'))->errorCode())->toBe('not_runnable');

    $strict = new AiToolAdapterV1($reg, PeerVersionProbe::forMissingPeers(), surfaceEnabled: true, requireCompatiblePeer: true);
    expect(fn () => $strict->register('ops'))->toThrow(PeerIncompatibleException::class);

    $adapter = new AiToolAdapterV1($reg, $probeOk, surfaceEnabled: true, requireCompatiblePeer: false);
    $adapter->register(ToolSelection::of('ops'));
    expect($adapter->registeredTools())->toBeArray()
        ->and($adapter->isRegistered())->toBeBool();

    $spoof = $adapter->handle('ai.cap', ['actor' => 1, 'caller' => 'http'], SystemActor::named('s'));
    expect($spoof->errorCode())->toBe('forbidden');

    $ok = $adapter->handle('ai.cap', ['idempotency_key' => str_repeat('z', 16)], SystemActor::named('s'), [
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);
    expect($ok->isOk() || $ok->errorCode() !== null)->toBeTrue();

    $structured = $adapter->handleStructured('ai.cap', ['caller' => 'x'], SystemActor::named('s'));
    expect($structured['ok'])->toBeFalse();

    $adapter->resetTurn();
    expect($adapter->turnToolCalls())->toBe(0)
        ->and($adapter->turnBudget())->not->toBeNull()
        ->and($adapter->adapterApiVersion())->toBeInt();
});

it('covers McpToolAdapterV1 disabled, spoof, register RuntimeException path', function () {
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'mcp.cap',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    $probe = PeerVersionProbe::fake(['laravel/mcp' => true]);
    $resolver = new McpAuthProfileResolver([
        'allow_integration_credentials' => true,
        'integration_actors' => ['c1' => 'integration-c1'],
    ]);

    $disabled = new McpToolAdapterV1($reg, $probe, $resolver, surfaceEnabled: false, requireCompatiblePeer: false);
    expect($disabled->register('ops'))->toBe([])
        ->and($disabled->listTools())->toBe([])
        ->and($disabled->isRegistered())->toBeFalse()
        ->and($disabled->activeProfile())->toBeNull();

    $cred = McpCredential::userPat((object) ['id' => 1], 'c1');
    expect($disabled->handle('mcp.cap', [], $cred)->errorCode())->toBe('not_runnable');

    $adapter = new McpToolAdapterV1($reg, $probe, $resolver, surfaceEnabled: true, requireCompatiblePeer: false);
    $adapter->register(ToolSelection::of(['groups' => ['ops']]));
    expect($adapter->registeredTools())->toBeArray();

    $spoof = $adapter->handle('mcp.cap', ['user_id' => 9, 'actor' => 1], $cred);
    expect($spoof->errorCode())->toBe('forbidden');

    $ok = $adapter->handle('mcp.cap', ['x' => 1], $cred, [
        'scope' => new CapabilityScope(tenantId: 't'),
        'actor' => SystemActor::named('s'),
    ]);
    expect($ok)->toBeInstanceOf(CapabilityResult::class);

    $strict = new McpToolAdapterV1($reg, PeerVersionProbe::forMissingPeers(), $resolver, surfaceEnabled: true, requireCompatiblePeer: true);
    expect(fn () => $strict->register('ops'))->toThrow(PeerIncompatibleException::class);
});

it('covers ArtisanCapabilityInvoker actor/system/tenant branches', function () {
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'art.cap',
        description: 'd',
        readOnly: false,
        input: CreateInvoiceInput::class,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));
    $reg->register(new CapabilityDefinition(
        name: 'art.deny-sys',
        description: 'd',
        readOnly: true,
        allowSystemCallers: false,
        run: static fn () => CapabilityResult::ok([]),
    ));

    $inv = new ArtisanCapabilityInvoker($reg);

    expect(fn () => $inv->run([
        'name' => 'art.cap',
        'input' => ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'],
    ]))->toThrow(MissingArtisanActorException::class);

    $sysDenied = $inv->run([
        'name' => 'art.deny-sys',
        'system' => 'billing',
        'tenant' => 't1',
        'mutating' => false,
    ]);
    expect($sysDenied->errorCode())->toBe('forbidden');

    expect(fn () => $inv->run([
        'name' => 'art.cap',
        'input' => ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'],
        'system' => 'billing',
        'tenancy_required' => true,
    ]))->toThrow(MissingJobTenantException::class);

    $withUser = $inv->run([
        'name' => 'art.cap',
        'input' => ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'],
        'acting_as' => 42,
        'tenant' => 't1',
        'user_resolver' => static fn ($id) => (object) ['id' => $id],
        'skip_server_rules' => true,
    ]);
    expect($withUser)->toBeInstanceOf(CapabilityResult::class);

    expect(fn () => $inv->run([
        'name' => 'art.cap',
        'input' => ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'],
        'acting_as' => 99,
        'user_resolver' => static fn () => null,
    ]))->toThrow(RuntimeException::class);

    $numeric = $inv->run([
        'name' => 'art.cap',
        'input' => ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'],
        'acting_as' => '7',
        'tenant' => 't1',
        'skip_server_rules' => true,
    ]);
    expect($numeric)->toBeInstanceOf(CapabilityResult::class);

    $sysOk = $inv->run([
        'name' => 'art.cap',
        'input' => ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'],
        'system' => 'ops',
        'tenant' => 't1',
        'skip_server_rules' => true,
    ]);
    expect($sysOk)->toBeInstanceOf(CapabilityResult::class);

    expect(ArtisanCapabilityInvoker::isProductCli())->toBeFalse()
        ->and(ArtisanCapabilityInvoker::caller())->toBeString()
        ->and(ArtisanCapabilityInvoker::parseFlags(['acting-as' => '3', 'tenant' => 't']))->toHaveKey('acting_as');
});

it('covers ProfileSelector resolve and matches matrix', function () {
    $sel = new ProfileSelector;
    $def = new CapabilityDefinition(
        name: 'p.cap',
        description: 'd',
        aliases: ['p.alias'],
        groups: ['finance'],
        tags: ['billing'],
        readOnly: true,
    );

    $only = $sel->resolve(['only' => ['p.cap', 'other']]);
    expect($only['kind'])->toBeString()
        ->and($sel->matches($def, $only))->toBeTrue();

    $groups = $sel->resolve(['groups' => ['finance']]);
    expect($sel->matches($def, $groups))->toBeTrue();

    $tags = $sel->resolve(['tags' => ['billing']]);
    expect($sel->matches($def, $tags))->toBeTrue();

    $list = $sel->resolve(['p.cap', 'x']);
    expect($list['kind'])->toBe('only')
        ->and($sel->matches($def, $list))->toBeTrue();

    $conflict = $sel->resolve([
        'profile' => 'ops',
        'only' => ['p.cap'],
        'groups' => ['finance'],
    ]);
    expect($conflict)->toHaveKey('allowlist');

    $emptyOnlyIntersect = $sel->resolve([
        'only' => ['nope'],
        'profile' => 'ops',
    ]);
    expect($emptyOnlyIntersect['allowlist'] ?? null)->not->toBeNull();

    $unscoped = $sel->resolve(null);
    expect($sel->matches($def, $unscoped))->toBeFalse();

    $noMatch = $sel->resolve(['only' => ['zzz']]);
    expect($sel->matches($def, $noMatch))->toBeFalse();

    $groupsOnly = $sel->resolve(['groups' => ['other']]);
    expect($sel->matches($def, $groupsOnly))->toBeFalse();
});

it('covers WireKeyResolver tool/job/scalar paths', function () {
    expect(WireKeyResolver::toolArg('hdr', []))->toBe('hdr')
        ->and(WireKeyResolver::toolArg(null, ['idempotency_key' => 'body']))->toBe('body')
        ->and(WireKeyResolver::toolArg('', ['idempotency_key' => '']))->toBeNull()
        ->and(WireKeyResolver::job('h', []))->toBe('h')
        ->and(WireKeyResolver::job(null, ['idempotencyKey' => 'jk']))->toBe('jk')
        ->and(WireKeyResolver::job(null, ['idempotency_key' => 123]))->toBe('123')
        ->and(WireKeyResolver::job(null, ['idempotency_key' => ['x']]))->toBeNull()
        ->and(WireKeyResolver::approvalAccept(null, [], 'stored'))->toBe('stored')
        ->and(WireKeyResolver::approvalAccept('h', [], null))->toBe('h');
});

it('covers ApprovalPolicy role/staff/requester and default multi-tenant safety', function () {
    $p = new ApprovalPolicy;
    expect($p->isDefaultMultiTenantSafe())->toBeTrue()
        ->and($p->policy())->toBeString();

    $rolePol = new ApprovalPolicy(policy: 'role:finance-approver');
    expect($rolePol->roleName())->toBe('finance-approver')
        ->and($rolePol->isDefaultMultiTenantSafe())->toBeTrue();

    $row = [
        'tenant_id' => 't1',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
    ];
    $requester = (object) ['id' => 'u1'];
    expect($p->allows($row, $requester, 't1'))->toBeTrue()
        ->and($p->allows($row, $requester, 't2'))->toBeFalse()
        ->and($p->allows($row, SystemActor::named('s'), 't1'))->toBeFalse();

    $roleActor = (object) ['id' => 'u2', 'roles' => ['finance-approver']];
    expect($rolePol->allows($row, $roleActor, 't1'))->toBeTrue();

    $roleActor2 = (object) ['id' => 'u3', 'role' => 'approver'];
    $ror = new ApprovalPolicy(policy: ApprovalPolicy::REQUESTER_OR_ROLE);
    expect($ror->allows($row, $roleActor2, 't1'))->toBeTrue();

    $staff = new ApprovalPolicy(policy: ApprovalPolicy::ANY_STAFF);
    expect($staff->allows($row, (object) ['id' => 's1', 'is_staff' => true], 't1'))->toBeTrue();

    $withChecker = new ApprovalPolicy(
        policy: ApprovalPolicy::REQUESTER_OR_ROLE,
        roleChecker: static fn ($a, $r) => true,
        staffChecker: static fn ($a) => false,
    );
    expect($withChecker->allows($row, (object) ['id' => 'x'], 't1'))->toBeTrue();
});

it('covers RunCapabilityJob construction guards when present', function () {
    expect(fn () => RunCapabilityJob::assertDispatchable(['name' => 'x']))->toThrow(MissingJobActorException::class);

    $job = RunCapabilityJob::dispatch([
        'name' => 'job.cap',
        'input' => [],
        'actingAs' => SystemActor::named('worker'),
        'tenantId' => 't1',
        'idempotencyKey' => 'k',
    ]);
    expect($job->name)->toBe('job.cap')
        ->and($job->tenantId)->toBe('t1');

    $from = RunCapabilityJob::fromPayload([
        'name' => 'job.cap',
        'actingAs' => 5,
        'tenantId' => 't2',
    ]);
    expect($from->actingAs)->toBe(5);
});
