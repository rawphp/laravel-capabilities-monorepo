<?php

declare(strict_types=1);

use Rawphp\Capabilities\Approval\ApprovalManager;
use Rawphp\CapabilitiesMessaging\Telegram\CallbackHandler;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

function pendingTenantApproval(string $id, ?string $tenantId): ApprovalManager
{
    $approvals = H::approvals();
    $approvals->request([
        'id' => $id,
        'capability_name' => 'x',
        'tenant_id' => $tenantId,
        'requester_actor_type' => 'user',
        'requester_actor_id' => 'u1',
        'original_caller' => 'http',
        'input_json' => [],
    ]);

    return $approvals;
}

it('fail: approver linked under another tenant cannot decide the approval [D-006] [D-003]', function (string $action) {
    $approvals = pendingTenantApproval('ap-tenant-x', 'tenant-a');
    $identity = H::identity();
    $identity->link('42', 'u1', 'tenant-b');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);

    $r = $handler->handle(H::signer()->sign('ap-tenant-x', $action), ['id' => '42']);

    expect($r)->toBe(['status' => 'forbidden', 'message' => 'unlinked_approver'])
        ->and($approvals->find('ap-tenant-x')['status'])->toBe('pending');
})->with(['accept', 'reject']);

it('happy: approver linked under the approval tenant decides it [D-006]', function () {
    $approvals = pendingTenantApproval('ap-tenant-ok', 'tenant-a');
    $identity = H::identity();
    $identity->link('42', 'u1', 'tenant-a');
    $handler = new CallbackHandler(H::signer(), $identity, $approvals);

    $r = $handler->handle(H::signer()->sign('ap-tenant-ok', 'accept'), ['id' => '42']);

    expect($r['status'])->toBe('ok');
});

it('fail: unlinked Telegram user cannot decide the approval [D-006]', function () {
    $approvals = pendingTenantApproval('ap-unlinked', 'tenant-a');
    $handler = new CallbackHandler(H::signer(), H::identity(), $approvals);

    $r = $handler->handle(H::signer()->sign('ap-unlinked', 'accept'), ['id' => '42']);

    expect($r)->toBe(['status' => 'forbidden', 'message' => 'unlinked_approver'])
        ->and($approvals->find('ap-unlinked')['status'])->toBe('pending');
});
