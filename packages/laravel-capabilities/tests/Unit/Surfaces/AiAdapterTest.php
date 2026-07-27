<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Ai\AiToolAdapterV1;
use Rawphp\Capabilities\Adapters\PeerVersionProbe;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;

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
    $h = AdapterHelpers::harness();
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
