<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\PipelineScenario as S;

/**
 * MSG-003 / D-019: every processing failure point fails closed without a second mutation path,
 * and is observable (error code + failed-job tags on the result; see FailureLoggingTest for logs).
 */
it('fail: process update handles failure at invalid_update_shape without domain bypass [MSG-003]', function () {
    $s = S::failingAt('invalid_update_shape');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('edge: process update failure at invalid_update_shape is observable in logs or failed jobs [D-019]', function () {
    $r = S::failingAt('invalid_update_shape')->run();

    expect($r['failed_step'])->not->toBeEmpty()
        ->and($r['failed_step'])->toBe('invalid_update_shape');
});

it('fail: process update handles failure at unknown_chat without domain bypass [MSG-003]', function () {
    $s = S::failingAt('unknown_chat');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('edge: process update failure at unknown_chat is observable in logs or failed jobs [D-019]', function () {
    $r = S::failingAt('unknown_chat')->run();

    expect($r['failed_step'])->not->toBeEmpty()
        ->and($r['tags']['channel'])->toBe('telegram')
        ->and($r['tags'])->toHaveKeys(['chat_id', 'update_id']);
});

it('fail: process update handles failure at identity_unresolved without domain bypass [MSG-003]', function () {
    $s = S::failingAt('identity_unresolved');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('edge: process update failure at identity_unresolved is observable in logs or failed jobs [D-019]', function () {
    $r = S::failingAt('identity_unresolved')->run();

    expect($r['failed_step'])->not->toBeEmpty()
        ->and($r['tags']['channel'])->toBe('telegram')
        ->and($r['tags'])->toHaveKeys(['chat_id', 'update_id']);
});

it('fail: process update handles failure at thread_store_failure without domain bypass [MSG-003]', function () {
    $s = S::failingAt('thread_store_failure');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('edge: process update failure at thread_store_failure is observable in logs or failed jobs [D-019]', function () {
    $r = S::failingAt('thread_store_failure')->run();

    expect($r['failed_step'])->not->toBeEmpty()
        ->and($r['tags']['channel'])->toBe('telegram')
        ->and($r['tags'])->toHaveKeys(['chat_id', 'update_id']);
});

it('fail: process update handles failure at ingress_failure without domain bypass [MSG-003]', function () {
    $s = S::failingAt('ingress_failure');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('edge: process update failure at ingress_failure is observable in logs or failed jobs [D-019]', function () {
    $r = S::failingAt('ingress_failure')->run();

    expect($r['failed_step'])->not->toBeEmpty()
        ->and($r['tags']['channel'])->toBe('telegram')
        ->and($r['tags'])->toHaveKeys(['chat_id', 'update_id']);
});

it('fail: process update handles failure at agent_failure without domain bypass [MSG-003]', function () {
    $s = S::failingAt('agent_failure');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('edge: process update failure at agent_failure is observable in logs or failed jobs [D-019]', function () {
    $r = S::failingAt('agent_failure')->run();

    expect($r['failed_step'])->not->toBeEmpty()
        ->and($r['tags']['channel'])->toBe('telegram')
        ->and($r['tags'])->toHaveKeys(['chat_id', 'update_id']);
});

it('fail: process update handles failure at tool_registry_failure without domain bypass [MSG-003]', function () {
    $s = S::failingAt('tool_registry_failure');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(1);
});

it('edge: process update failure at tool_registry_failure is observable in logs or failed jobs [D-019]', function () {
    $r = S::failingAt('tool_registry_failure')->run();

    expect($r['failed_step'])->not->toBeEmpty()
        ->and($r['tags']['channel'])->toBe('telegram')
        ->and($r['tags'])->toHaveKeys(['chat_id', 'update_id']);
});

it('fail: process update handles failure at reply_failure without domain bypass [MSG-003]', function () {
    $s = S::failingAt('reply_failure');

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('edge: process update failure at reply_failure is observable in logs or failed jobs [D-019]', function () {
    $r = S::failingAt('reply_failure')->run();

    expect($r['failed_step'])->not->toBeEmpty()
        ->and($r['tags']['channel'])->toBe('telegram')
        ->and($r['tags'])->toHaveKeys(['chat_id', 'update_id']);
});
