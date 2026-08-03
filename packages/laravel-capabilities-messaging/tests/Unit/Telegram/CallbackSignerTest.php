<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\CapabilitiesMessaging\Support\LinkedUser;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Telegram\TelegramCallbackSigner;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: signed callback over approval_id action exp verifies [D-006]', function () {
    $s = H::signer();
    $p = $s->sign('a1', 'accept', 'hint', 1000);
    expect($s->verify($p, 1000))->toBeTrue();
});

it('fail: tampered token invalid [D-006]', function () {
    $s = H::signer();
    $p = $s->sign('a1', 'accept', null, 1000);
    $p['approval_id'] = 'a2';
    expect($s->verify($p, 1000))->toBeFalse();
});

it('fail: expired token invalid [D-006]', function () {
    $s = H::signer();
    $p = $s->sign('a1', 'accept', null, 1000);
    expect($s->verify($p, 1000 + 901))->toBeFalse();
});

it('fail: unsigned approval id only is rejected [D-006]', function () {
    $s = H::signer();
    expect(fn () => $s->rejectUnsignedApprovalId('5'))->toThrow(RuntimeException::class);
});

it('happy: callback does not embed capability input [D-006]', function () {
    $p = H::signer()->sign('a1', 'accept');
    expect($p)->not->toHaveKey('input')->and($p)->not->toHaveKey('input_json');
});

it('happy: after executed rejected expired callback is no-op already handled [D-006]', function () {
    $approvals = H::approvals();
    $row = $approvals->request([
        'id' => 'ap-1',
        'capability_name' => 'x',
        'status' => 'pending',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => ['n' => 1],
    ]);
    $approvals->store()->update('ap-1', ['status' => 'executed']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $p = H::signer()->sign('ap-1', 'accept');
    $r = $handler->handle($p, ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('fail: Telegram user not linked to allowed approver cannot approve [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'ap-2',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $handler = H::callbackHandler(H::identity(), $approvals);
    $p = H::signer()->sign('ap-2', 'accept');
    $r = $handler->handle($p, ['id' => 'unlinked']);
    expect($r['status'])->toBe('forbidden');
});

it('happy: linked allowed approver routes to ApprovalManager accept reject [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'ap-3',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => ['a' => 1],
        'tenant_id' => null,
    ]);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $p = H::signer()->sign('ap-3', 'accept');
    $r = $handler->handle($p, ['id' => '42']);
    expect($r['status'])->toBe('ok');
});

it('edge: callback TTL uses configured telegram_callback_ttl_seconds [D-006]', function () {
    $cfg = H::config(['telegram' => ['callback_ttl_seconds' => 120]]);
    $s = new TelegramCallbackSigner($cfg->callbackSecret(), $cfg->callbackTtlSeconds());
    expect($s->ttlSeconds())->toBe(120);
    $p = $s->sign('a', 'accept', null, 1000);
    expect($p['exp'])->toBe(1120);
});

it('fail: callback does not carry capability input payload [D-006]', function () {
    $s = H::signer();
    expect(fn () => $s->assertSafePayload(['input_json' => []]))->toThrow(RuntimeException::class);
});

it('happy: HTTP and Telegram accept share ApprovalManager state machine [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'ap-shared',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $user = new LinkedUser('u1');
    $http = $approvals->accept('ap-shared', $user);
    expect($http)->toBeInstanceOf(CapabilityResult::class);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('ap-shared', 'accept'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('fail: forged HMAC rejected [D-006]', function () {
    $s = H::signer();
    $p = $s->sign('a1', 'accept', null, 1000);
    $p['sig'] = 'deadbeef';
    expect($s->verify($p, 1000))->toBeFalse();
});

it('fail: action other than accept reject rejected [D-006]', function () {
    $s = H::signer();
    expect(fn () => $s->sign('a1', 'explode'))->toThrow(RuntimeException::class);
    expect($s->verify([
        'approval_id' => 'a1',
        'action' => 'explode',
        'exp' => time() + 100,
        'sig' => 'x',
    ]))->toBeFalse();
});

it('happy: server loads input only from approval row [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'ap-in',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => ['from_row' => true],
    ]);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $p = H::signer()->sign('ap-in', 'accept');
    $r = $handler->handle($p, ['id' => '42']);
    expect($r['loaded_input_from_row'] ?? false)->toBeTrue();
    expect($r['callback_had_input'] ?? true)->toBeFalse();
});
