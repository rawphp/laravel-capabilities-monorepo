<?php

declare(strict_types=1);

use Rawphp\Capabilities\Contracts\ApprovalNotifier;
use Rawphp\CapabilitiesMessaging\Tests\Fixtures\MessagingHelpers as H;

it('happy: notifyPending sends message with signed buttons [D-006]', function () {
    $bot = H::bot();
    $n = H::notifier(null, $bot);
    $n->notifyPending([
        'id' => 'appr-1',
        'capability_name' => 'billing.void',
        'summary' => 'void invoice',
        'messaging' => ['chat_id' => '55'],
    ]);
    expect($n->notified())->toHaveCount(1);
    expect($bot->calls()[0]['method'])->toBe('sendMessage');
    expect($bot->calls()[0]['args']['signed_buttons'] ?? false)->toBeTrue();
    expect($bot->calls()[0]['args']['accept_payload']['sig'] ?? null)->not->toBeNull();
});

it('happy: notifier never executes capability [D-006]', function () {
    $n = H::notifier();
    $n->notifyPending(['id' => 'a', 'messaging' => ['chat_id' => '1']]);
    expect($n->capabilityExecuteCount())->toBe(0);
});

it('edge: expired approval may edit message to expired [D-006]', function () {
    $bot = H::bot();
    $n = H::notifier(null, $bot);
    $n->editMessage([
        'id' => 'a',
        'messaging' => ['chat_id' => '1', 'message_id' => 9],
    ], 'expired');
    expect($n->edits()[0]['text'])->toBe('expired');
    expect($bot->calls()[0]['method'])->toBe('editMessageText');
});

it('fail: notify with invalid approval id does not execute capability [D-006]', function () {
    $n = H::notifier();
    $n->notifyPending(['id' => '', 'messaging' => ['chat_id' => '1']]);
    expect($n->capabilityExecuteCount())->toBe(0);
});

it('happy: notifier routes accept reject only through ApprovalManager [D-006]', function () {
    $n = H::notifier();
    $n->notifyPending(['id' => 'a1', 'messaging' => ['chat_id' => '1']]);
    expect($n)->toBeInstanceOf(ApprovalNotifier::class);
    expect(method_exists($n, 'accept'))->toBeFalse();
});

it('fail: notifier does not call domain services [D-007]', function () {
    $n = H::notifier();
    $n->notifyPending(['id' => 'a1', 'messaging' => ['chat_id' => '1']]);
    expect($n->domainServiceCalls())->toBe(0);
});
