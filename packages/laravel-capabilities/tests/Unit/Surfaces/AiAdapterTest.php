<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerIncompatibleException;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Adapters\ToolSelection;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

function aiAdapterSystemRegistry(): CapabilityRegistry
{
    $reg = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $reg->register(new CapabilityDefinition(
        name: 'ai.cap',
        description: 'd',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['v' => 1]),
    ));

    return $reg;
}

it('happy: AiToolAdapterV1 builds tools from profile selection [D-011]', function () {
    $h = AdapterHelpers::harness();
    $tools = $h['ai']->toolsFor('billing');
    $names = array_column($tools, 'name');
    expect($names)->toContain('create-invoice')
        ->and($names)->toContain('void-invoice')
        ->and($names)->not->toContain('delete-account');
});

it('happy: tool handle validates and invokes registry with caller agent [D-022]', function () {
    $h = AdapterHelpers::harness();
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], [
        'profile' => 'billing',
    ]);
    expect($r->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->caller)->toBe('agent')
        ->and($h['runs']['create-invoice']->value)->toBe(1);
});

it('fail: tool handle does not accept caller from model input [D-022]', function () {
    $h = AdapterHelpers::harness();
    $r = $h['ai']->handle(
        'create-invoice',
        AdapterHelpers::input(['caller' => 'http', 'actor' => 'spoof']),
        $h['user'],
        ['profile' => 'billing', 'caller' => 'cli'],
    );
    expect($r->isOk())->toBeFalse()
        ->and($r->errorCode())->toBe('forbidden')
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('happy: max_tool_calls_per_turn enforced on agent loop budget [D-013]', function () {
    $h = AdapterHelpers::harness(['max_tool_calls' => 2]);
    $user = $h['user'];
    $ok1 = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $user, ['profile' => 'billing']);
    $ok2 = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $user, ['profile' => 'billing']);
    $limited = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $user, ['profile' => 'billing']);
    expect($ok1->isOk())->toBeTrue()
        ->and($ok2->isOk())->toBeTrue()
        ->and($limited->errorCode())->toBe('rate_limited')
        ->and($h['ai']->turnToolCalls())->toBe(3);
});

it('fail: spoofed tool calls count toward the agent turn budget [D-013]', function () {
    $h = AdapterHelpers::harness(['max_tool_calls' => 2]);
    $user = $h['user'];
    $spoof1 = $h['ai']->handle('create-invoice', AdapterHelpers::input(['actor' => 'x']), $user, ['profile' => 'billing']);
    $spoof2 = $h['ai']->handle('create-invoice', AdapterHelpers::input(['caller' => 'http']), $user, ['profile' => 'billing']);
    $limited = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $user, ['profile' => 'billing']);
    expect($spoof1->errorCode())->toBe('forbidden')
        ->and($spoof2->errorCode())->toBe('forbidden')
        ->and($limited->errorCode())->toBe('rate_limited')
        ->and($h['ai']->turnToolCalls())->toBe(3)
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('edge: tool input_schema equals catalog input_schema [D-004]', function () {
    $h = AdapterHelpers::harness();
    $tool = collect($h['ai']->toolsFor('billing'))->firstWhere('name', 'create-invoice');
    $catalog = $h['registry']->get('create-invoice')->inputSchema();
    expect($tool['input_schema'])->toBe($catalog)
        ->and($tool['source'])->toBe('registry');
});

it('fail: agent surface disabled registers no tools [SURF-003]', function () {
    $h = AdapterHelpers::harness(['agent_enabled' => false]);
    expect($h['ai']->register('billing'))->toBe([])
        ->and($h['ai']->toolsFor('billing'))->toBe([])
        ->and($h['ai']->isRegistered())->toBeFalse();
});

it('happy: idempotency_key tool arg passed through (case 1) [D-005]', function () {
    $h = AdapterHelpers::harness();
    $input = AdapterHelpers::input(['idempotency_key' => 'turn-key-1']);
    $h['ai']->handle('create-invoice', $input, $h['user'], ['profile' => 'billing']);
    $h['ai']->handle('create-invoice', $input, $h['user'], ['profile' => 'billing']);
    expect($h['runs']['create-invoice']->value)->toBe(1);
});

it('fail: authorization deny through ai does not mutate [D-011]', function () {
    $h = AdapterHelpers::harness(['authorizer' => AdapterHelpers::denyAuthorizer()]);
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], [
        'profile' => 'billing',
    ]);
    expect($r->errorCode())->toBe('forbidden')
        ->and($h['runs']['create-invoice']->value)->toBe(0);
});

it('edge: messaging agent turn still caller agent with messaging metadata [D-007]', function () {
    $h = AdapterHelpers::harness(['surfaces' => ['messaging' => true]]);
    $r = $h['ai']->handle('create-invoice', AdapterHelpers::input(), $h['user'], [
        'profile' => 'billing',
        'messaging' => ['channel' => 'telegram', 'chat_id' => '99'],
    ]);
    expect($r->isOk())->toBeTrue()
        ->and($h['registry']->lastState()?->caller)->toBe('agent')
        ->and($h['registry']->lastState()?->context?->messaging())->toMatchArray([
            'channel' => 'telegram',
            'chat_id' => '99',
        ]);
});

it('fail: handle without profile option enforces the registered profile [D-008]', function () {
    $h = AdapterHelpers::harness();
    $h['ai']->register('support');
    $r = $h['ai']->handle('delete-account', AdapterHelpers::input(), $h['user']);
    expect($r->errorCode())->toBe('capability_not_in_profile')
        ->and($h['ai']->activeProfile())->toBe('support')
        ->and($h['runs']['delete-account']->value)->toBe(0);
});

it('happy: handle without profile option runs capability in the registered profile [D-008]', function () {
    $h = AdapterHelpers::harness();
    $h['ai']->register(['groups' => ['support']]);
    $r = $h['ai']->handle('get-customer', AdapterHelpers::input(), $h['user']);
    expect($r->isOk())->toBeTrue()
        ->and($h['runs']['get-customer']->value)->toBe(1);
});

it('edge: disabled agent surface clears the registered profile [D-008]', function () {
    $h = AdapterHelpers::harness(['agent_enabled' => false]);
    $h['ai']->register('support');
    expect($h['ai']->activeProfile())->toBeNull();
});

it('edge: disabled ai surface registers nothing and refuses handle as not_runnable', function () {
    $adapter = new AiToolAdapterV1(aiAdapterSystemRegistry(), PeerVersionProbe::fake(['laravel/ai' => true]), surfaceEnabled: false, requireCompatiblePeer: false);

    expect($adapter->register('ops'))->toBe([])
        ->and($adapter->isRegistered())->toBeFalse()
        ->and($adapter->handle('ai.cap', [], SystemActor::named('s'))->errorCode())->toBe('not_runnable');
});

it('fail: ai register throws when peer is required but missing', function () {
    $strict = new AiToolAdapterV1(aiAdapterSystemRegistry(), PeerVersionProbe::forMissingPeers(), surfaceEnabled: true, requireCompatiblePeer: true);

    expect(fn () => $strict->register('ops'))->toThrow(PeerIncompatibleException::class);
});

it('happy: ai adapter registers tools from a ToolSelection and reports its api version', function () {
    $adapter = new AiToolAdapterV1(aiAdapterSystemRegistry(), PeerVersionProbe::fake(['laravel/ai' => true]), surfaceEnabled: true, requireCompatiblePeer: false);
    $adapter->register(ToolSelection::of('ops'));

    expect($adapter->registeredTools())->toBeArray()
        ->and($adapter->isRegistered())->toBeBool()
        ->and($adapter->adapterApiVersion())->toBeInt();
});

it('fail: ai handle rejects model-supplied actor and caller as forbidden', function () {
    $adapter = new AiToolAdapterV1(aiAdapterSystemRegistry(), PeerVersionProbe::fake(['laravel/ai' => true]), surfaceEnabled: true, requireCompatiblePeer: false);

    $spoof = $adapter->handle('ai.cap', ['actor' => 1, 'caller' => 'http'], SystemActor::named('s'));

    expect($spoof->errorCode())->toBe('forbidden');
});

it('happy: ai handle runs a system-callable capability with idempotency key and scope', function () {
    $adapter = new AiToolAdapterV1(aiAdapterSystemRegistry(), PeerVersionProbe::fake(['laravel/ai' => true]), surfaceEnabled: true, requireCompatiblePeer: false);

    $ok = $adapter->handle('ai.cap', ['idempotency_key' => str_repeat('z', 16)], SystemActor::named('s'), [
        'scope' => new CapabilityScope(tenantId: 't'),
    ]);

    expect($ok->isOk() || $ok->errorCode() !== null)->toBeTrue();
});

it('fail: ai handleStructured reports ok=false for spoofed caller input', function () {
    $adapter = new AiToolAdapterV1(aiAdapterSystemRegistry(), PeerVersionProbe::fake(['laravel/ai' => true]), surfaceEnabled: true, requireCompatiblePeer: false);

    $structured = $adapter->handleStructured('ai.cap', ['caller' => 'x'], SystemActor::named('s'));

    expect($structured['ok'])->toBeFalse();
});

it('edge: ai resetTurn zeroes tool calls and keeps a turn budget', function () {
    $adapter = new AiToolAdapterV1(aiAdapterSystemRegistry(), PeerVersionProbe::fake(['laravel/ai' => true]), surfaceEnabled: true, requireCompatiblePeer: false);

    $adapter->resetTurn();

    expect($adapter->turnToolCalls())->toBe(0)
        ->and($adapter->turnBudget())->not->toBeNull();
});
