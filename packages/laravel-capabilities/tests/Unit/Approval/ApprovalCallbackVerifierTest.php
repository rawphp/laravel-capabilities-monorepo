<?php

declare(strict_types=1);

// ApprovalCallbackVerifier signing.

use Rawphp\Capabilities\Approval\ApprovalCallbackVerifier;

it('ApprovalCallbackVerifier verifies signed payloads and rejects missing, expired, or forged signatures', function () {
    $v = new ApprovalCallbackVerifier('secret', 60);
    $payload = [
        'approval_id' => 'ap-1',
        'action' => 'accept',
        'exp' => time() + 120,
        'approver_hint' => 'alice',
    ];
    $sig = $v->sign($payload);
    expect($v->verify(array_merge($payload, ['sig' => $sig])))->toBeTrue()
        ->and($v->verify($payload))->toBeFalse() // missing sig
        ->and($v->verify(['sig' => 'x']))->toBeFalse() // missing fields
        ->and($v->verify(array_merge($payload, ['sig' => $sig, 'exp' => time() - 10])))->toBeFalse()
        ->and($v->verify(array_merge($payload, ['sig' => 'deadbeef'])))->toBeFalse();

    expect(fn () => $v->acceptUnsignedIdOnly('ap-1'))->toThrow(RuntimeException::class);
});
