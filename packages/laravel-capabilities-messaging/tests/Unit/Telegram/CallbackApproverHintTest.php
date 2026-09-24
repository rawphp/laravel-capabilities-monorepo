<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\CapabilitiesMessaging\Identity\IdentityLinker;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

function pendingApprovalForHint(string $id): ApprovalManager
{
    $approvals = H::approvals();
    $approvals->request([
        'id' => $id,
        'capability_name' => 'x',
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);

    return $approvals;
}

it('fail: signed callback bound to another approver is forbidden for a different linked user [D-006]', function (string $action) {
    $approvals = pendingApprovalForHint('ap-hint');
    $identity = H::identity();
    $identity->link('99', 'u2');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);

    $r = $handler->handle(H::signer()->sign('ap-hint', $action, 'u1'), ['id' => '99']);

    expect($r)->toBe(['status' => 'forbidden', 'message' => 'approver_mismatch'])
        ->and($approvals->find('ap-hint')['status'])->toBe('pending');
})->with(['accept', 'reject']);

it('happy: signed callback bound to an approver is actioned by that linked user [D-006]', function () {
    $approvals = pendingApprovalForHint('ap-hint-ok');
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);

    $r = $handler->handle(H::signer()->sign('ap-hint-ok', 'accept', 'u1'), ['id' => '42']);

    expect($r['status'])->toBe('ok');
});

it('edge: hint is the product principal id, not the Telegram user id [D-006]', function () {
    $approvals = pendingApprovalForHint('ap-hint-tg');
    $identity = H::identity();
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);

    $r = $handler->handle(H::signer()->sign('ap-hint-tg', 'accept', '42'), ['id' => '42']);

    expect($r)->toBe(['status' => 'forbidden', 'message' => 'approver_mismatch']);
});

it('edge: hint matches a principal exposing only getAuthIdentifier [D-006]', function () {
    $approvals = pendingApprovalForHint('ap-hint-auth');
    $identity = new IdentityLinker(H::config(), fn (string $id): object => new class($id)
    {
        public function __construct(private readonly string $key) {}

        public function getAuthIdentifier(): string
        {
            return $this->key;
        }
    });
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);

    expect($handler->handle(H::signer()->sign('ap-hint-auth', 'accept', 'u1'), ['id' => '42'])['status'])->toBe('ok')
        ->and($handler->handle(H::signer()->sign('ap-hint-auth', 'accept', 'u9'), ['id' => '42'])['message'])->toBe('approver_mismatch');
});

it('fail: hinted callback is forbidden when the principal has no id [D-006]', function () {
    $approvals = pendingApprovalForHint('ap-hint-anon');
    $identity = new IdentityLinker(H::config(), fn (): object => new stdClass);
    $identity->link('42', 'u1');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);

    $r = $handler->handle(H::signer()->sign('ap-hint-anon', 'accept', 'u1'), ['id' => '42']);

    expect($r)->toBe(['status' => 'forbidden', 'message' => 'approver_mismatch']);
});
