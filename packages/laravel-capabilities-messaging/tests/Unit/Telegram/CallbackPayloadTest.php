<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: callback payload includes approval_id [D-006]', function () {
    expect(H::signer()->sign('a1', 'accept'))->toHaveKey('approval_id');
});

it('happy: callback payload includes action [D-006]', function () {
    expect(H::signer()->sign('a1', 'reject')['action'])->toBe('reject');
});

it('happy: callback payload includes exp [D-006]', function () {
    expect(H::signer()->sign('a1', 'accept'))->toHaveKey('exp');
});

it('happy: callback payload includes approver_hint [D-006]', function () {
    expect(H::signer()->sign('a1', 'accept', 'h'))->toHaveKey('approver_hint');
});

it('happy: callback payload includes signature [D-006]', function () {
    expect(H::signer()->sign('a1', 'accept'))->toHaveKey('sig');
});

it('fail: callback payload must not include capability input [D-006]', function () {
    expect(fn () => H::signer()->assertSafePayload(['input' => []]))->toThrow(RuntimeException::class);
});

it('fail: callback payload must not include raw bot token [D-006]', function () {
    expect(fn () => H::signer()->assertSafePayload(['bot_token' => 'x']))->toThrow(RuntimeException::class);
});
