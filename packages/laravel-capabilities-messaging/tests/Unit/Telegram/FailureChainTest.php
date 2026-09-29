<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\PipelineScenario as S;

/**
 * MSG-003 / D-007: each failure in the messaging chain fails closed (no reply, no second
 * mutation path). Tool invocations only ever happen through the capability bus.
 */
it('fail: messaging chain fails closed at bad_secret [MSG-003]', function () {
    $s = S::failingAt('bad_secret');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['failed_step'])->toBe('verify_webhook_secret')
        ->and($s->bot->calls())->toBe([]);
});

it('fail: messaging chain at bad_secret never bypasses registry for mutation [D-007]', function () {
    $s = S::failingAt('bad_secret');

    $s->run();

    // Only the bus may run a capability; at most the one refused tool call reaches it.
    expect($s->registry->invokeCount())->toBe(0);
});

it('fail: messaging chain fails closed at unlinked_user [MSG-003]', function () {
    $s = S::failingAt('unlinked_user');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('identity_unresolved')
        ->and($s->bot->calls())->toBe([]);
});

it('fail: messaging chain at unlinked_user never bypasses registry for mutation [D-007]', function () {
    $s = S::failingAt('unlinked_user');

    $s->run();

    // Only the bus may run a capability; at most the one refused tool call reaches it.
    expect($s->registry->invokeCount())->toBe(0);
});

it('fail: messaging chain fails closed at profile_missing [MSG-003]', function () {
    $s = S::failingAt('profile_missing');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toContain('agent_profile is required')
        ->and($s->bot->calls())->toBe([]);
});

it('fail: messaging chain at profile_missing never bypasses registry for mutation [D-007]', function () {
    $s = S::failingAt('profile_missing');

    $s->run();

    // Only the bus may run a capability; at most the one refused tool call reaches it.
    expect($s->registry->invokeCount())->toBe(0);
});

it('fail: messaging chain fails closed at tool_not_in_profile [MSG-003]', function () {
    $s = S::failingAt('tool_not_in_profile');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('tool_not_in_profile')
        ->and($s->bot->calls())->toBe([]);
});

it('fail: messaging chain at tool_not_in_profile never bypasses registry for mutation [D-007]', function () {
    $s = S::failingAt('tool_not_in_profile');

    $s->run();

    // Only the bus may run a capability; at most the one refused tool call reaches it.
    expect($s->registry->invokeCount())->toBe(0);
});

it('fail: messaging chain fails closed at registry_forbidden [MSG-003]', function () {
    $s = S::failingAt('registry_forbidden');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('registry_forbidden')
        ->and($s->bot->calls())->toBe([]);
});

it('fail: messaging chain at registry_forbidden never bypasses registry for mutation [D-007]', function () {
    $s = S::failingAt('registry_forbidden');

    $s->run();

    // Only the bus may run a capability; at most the one refused tool call reaches it.
    expect($s->registry->invokeCount())->toBe(1);
});

it('fail: messaging chain fails closed at registry_validation [MSG-003]', function () {
    $s = S::failingAt('registry_validation');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('validation_failed')
        ->and($s->bot->calls())->toBe([]);
});

it('fail: messaging chain at registry_validation never bypasses registry for mutation [D-007]', function () {
    $s = S::failingAt('registry_validation');

    $s->run();

    // Only the bus may run a capability; at most the one refused tool call reaches it.
    expect($s->registry->invokeCount())->toBe(1);
});

it('fail: messaging chain fails closed at approval_required [MSG-003]', function () {
    $s = S::failingAt('approval_required');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toBe('approval_required')
        ->and($s->bot->calls())->toBe([]);
});

it('fail: messaging chain at approval_required never bypasses registry for mutation [D-007]', function () {
    $s = S::failingAt('approval_required');

    $s->run();

    // Only the bus may run a capability; at most the one refused tool call reaches it.
    expect($s->registry->invokeCount())->toBe(1);
});

it('fail: messaging chain fails closed at reply_send_fail [MSG-003]', function () {
    $s = S::failingAt('reply_send_fail');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['error'])->toStartWith('reply_send_fail')
        ->and($r['steps'])->not->toContain('conversation_reply');
});

it('fail: messaging chain at reply_send_fail never bypasses registry for mutation [D-007]', function () {
    $s = S::failingAt('reply_send_fail');

    $s->run();

    // Only the bus may run a capability; at most the one refused tool call reaches it.
    expect($s->registry->invokeCount())->toBe(0);
});
