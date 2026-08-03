<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('edge: callback routes to manager when action=accept status=pending [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-accept-pending',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-accept-pending', 'accept'), ['id' => '42']);
    expect($r['status'])->toBe('ok');
});

it('happy: callback no-op already handled when action=accept status=approved [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-accept-approved',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $approvals->store()->update('st-accept-approved', ['status' => 'approved']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-accept-approved', 'accept'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('happy: callback no-op already handled when action=accept status=rejected [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-accept-rejected',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $approvals->store()->update('st-accept-rejected', ['status' => 'rejected']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-accept-rejected', 'accept'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('happy: callback no-op already handled when action=accept status=expired [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-accept-expired',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $approvals->store()->update('st-accept-expired', ['status' => 'expired']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-accept-expired', 'accept'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('happy: callback no-op already handled when action=accept status=executed [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-accept-executed',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $approvals->store()->update('st-accept-executed', ['status' => 'executed']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-accept-executed', 'accept'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('edge: callback routes to manager when action=reject status=pending [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-reject-pending',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-reject-pending', 'reject'), ['id' => '42']);
    expect($r['status'])->toBe('ok');
});

it('happy: callback no-op already handled when action=reject status=approved [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-reject-approved',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $approvals->store()->update('st-reject-approved', ['status' => 'approved']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-reject-approved', 'reject'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('happy: callback no-op already handled when action=reject status=rejected [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-reject-rejected',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $approvals->store()->update('st-reject-rejected', ['status' => 'rejected']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-reject-rejected', 'reject'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('happy: callback no-op already handled when action=reject status=expired [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-reject-expired',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $approvals->store()->update('st-reject-expired', ['status' => 'expired']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-reject-expired', 'reject'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});

it('happy: callback no-op already handled when action=reject status=executed [D-006]', function () {
    $approvals = H::approvals();
    $approvals->request([
        'id' => 'st-reject-executed',
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);
    $approvals->store()->update('st-reject-executed', ['status' => 'executed']);
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);
    $r = $handler->handle(H::signer()->sign('st-reject-executed', 'reject'), ['id' => '42']);
    expect($r['status'])->toBe('already_handled');
});
