<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\PipelineScenario as S;

/**
 * Wired-path step order: webhook verify → queue → identity → thread → profile → ingress → tools → reply.
 */
const WIRED_STEP_ORDER = [
    'verify_webhook_secret',
    'queue_process_update',
    'resolve_identity',
    'map_thread',
    'agent_tools_profile',
    'conversation_ingress',
    'tool_calls_registry',
    'conversation_reply',
];

it('happy: step order 00 verify_secret [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['steps'])->toBe(WIRED_STEP_ORDER)
        ->and(array_search('verify_webhook_secret', $r['steps'], true))->toBe(array_search('verify_webhook_secret', WIRED_STEP_ORDER, true));
});

it('happy: step order 01 queue [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['steps'])->toBe(WIRED_STEP_ORDER)
        ->and(array_search('queue_process_update', $r['steps'], true))->toBe(array_search('queue_process_update', WIRED_STEP_ORDER, true));
});

it('happy: step order 02 resolve_identity [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['steps'])->toBe(WIRED_STEP_ORDER)
        ->and(array_search('resolve_identity', $r['steps'], true))->toBe(array_search('resolve_identity', WIRED_STEP_ORDER, true));
});

it('happy: step order 03 map_thread [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['steps'])->toBe(WIRED_STEP_ORDER)
        ->and(array_search('map_thread', $r['steps'], true))->toBe(array_search('map_thread', WIRED_STEP_ORDER, true));
});

it('happy: step order 04 ingress [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['steps'])->toBe(WIRED_STEP_ORDER)
        ->and(array_search('conversation_ingress', $r['steps'], true))->toBe(array_search('conversation_ingress', WIRED_STEP_ORDER, true));
});

it('happy: step order 05 agent [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['steps'])->toBe(WIRED_STEP_ORDER)
        ->and(array_search('agent_tools_profile', $r['steps'], true))->toBe(array_search('agent_tools_profile', WIRED_STEP_ORDER, true));
});

it('happy: step order 06 tools [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['steps'])->toBe(WIRED_STEP_ORDER)
        ->and(array_search('tool_calls_registry', $r['steps'], true))->toBe(array_search('tool_calls_registry', WIRED_STEP_ORDER, true));
});

it('happy: step order 07 reply [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['steps'])->toBe(WIRED_STEP_ORDER)
        ->and(array_search('conversation_reply', $r['steps'], true))->toBe(array_search('conversation_reply', WIRED_STEP_ORDER, true));
});

it('fail: tools not reached if verify_secret fails [MSG-003]', function () {
    $s = S::failingAt('bad_secret');

    $r = $s->run();

    expect($r['tools_reached'])->toBeFalse()
        ->and($r['failed_step'])->toBe('verify_webhook_secret')
        ->and($s->registry->invokeCount())->toBe(0);
});

it('fail: tools not reached if resolve_identity fails [MSG-003]', function () {
    $s = S::failingAt('identity_unresolved');

    $r = $s->run();

    expect($r['tools_reached'])->toBeFalse()
        ->and($r['steps'])->not->toContain('map_thread')
        ->and($s->registry->invokeCount())->toBe(0);
});
