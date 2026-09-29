<?php

declare(strict_types=1);

use Rawphp\CapabilitiesMessaging\Tests\Fixtures\PipelineScenario as S;

/**
 * MSG-003 pipeline steps over the wired path (webhook → queue → processor).
 * A failing step stops the pipeline: the next step never runs, and the bus is only reached from tool_calls_registry.
 */
it('happy: pipeline step verify_webhook_secret executes in order [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['ok'])->toBeTrue()
        ->and($r['steps'])->toContain('verify_webhook_secret');
});

it('fail: pipeline aborts before tools when step verify_webhook_secret fails if prior required [MSG-003]', function () {
    $s = S::failingAt(S::STEP_FAILURES['verify_webhook_secret']);

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['steps'])->not->toContain('queue_process_update')
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($s->bot->calls())->toBe([]);
    expect($r['tools_reached'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('happy: pipeline step queue_process_update executes in order [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['ok'])->toBeTrue()
        ->and($r['steps'])->toContain('queue_process_update');
});

it('fail: pipeline aborts before tools when step queue_process_update fails if prior required [MSG-003]', function () {
    $s = S::failingAt(S::STEP_FAILURES['queue_process_update']);

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['steps'])->not->toContain('resolve_identity')
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($s->bot->calls())->toBe([]);
    expect($r['tools_reached'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('happy: pipeline step resolve_identity executes in order [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['ok'])->toBeTrue()
        ->and($r['steps'])->toContain('resolve_identity');
});

it('fail: pipeline aborts before tools when step resolve_identity fails if prior required [MSG-003]', function () {
    $s = S::failingAt(S::STEP_FAILURES['resolve_identity']);

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['steps'])->not->toContain('map_thread')
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($s->bot->calls())->toBe([]);
    expect($r['tools_reached'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('happy: pipeline step map_thread executes in order [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['ok'])->toBeTrue()
        ->and($r['steps'])->toContain('map_thread');
});

it('fail: pipeline aborts before tools when step map_thread fails if prior required [MSG-003]', function () {
    $s = S::failingAt(S::STEP_FAILURES['map_thread']);

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['steps'])->not->toContain('agent_tools_profile')
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($s->bot->calls())->toBe([]);
    expect($r['tools_reached'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('happy: pipeline step conversation_ingress executes in order [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['ok'])->toBeTrue()
        ->and($r['steps'])->toContain('conversation_ingress');
});

it('fail: pipeline aborts before tools when step conversation_ingress fails if prior required [MSG-003]', function () {
    $s = S::failingAt(S::STEP_FAILURES['conversation_ingress']);

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['steps'])->not->toContain('tool_calls_registry')
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($s->bot->calls())->toBe([]);
    expect($r['tools_reached'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('happy: pipeline step agent_tools_profile executes in order [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['ok'])->toBeTrue()
        ->and($r['steps'])->toContain('agent_tools_profile');
});

it('fail: pipeline aborts before tools when step agent_tools_profile fails if prior required [MSG-003]', function () {
    $s = S::failingAt(S::STEP_FAILURES['agent_tools_profile']);

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['steps'])->not->toContain('conversation_ingress')
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($s->bot->calls())->toBe([]);
    expect($r['tools_reached'])->toBeFalse()
        ->and($s->registry->invokeCount())->toBe(0);
});

it('happy: pipeline step tool_calls_registry executes in order [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['ok'])->toBeTrue()
        ->and($r['steps'])->toContain('tool_calls_registry');
});

it('fail: pipeline aborts before tools when step tool_calls_registry fails if prior required [MSG-003]', function () {
    $s = S::failingAt(S::STEP_FAILURES['tool_calls_registry']);

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($s->bot->calls())->toBe([]);
});

it('happy: pipeline step conversation_reply executes in order [MSG-003]', function () {
    $r = S::happy()->run();

    expect($r['ok'])->toBeTrue()
        ->and($r['steps'])->toContain('conversation_reply');
});

it('fail: pipeline aborts before tools when step conversation_reply fails if prior required [MSG-003]', function () {
    $s = S::failingAt(S::STEP_FAILURES['conversation_reply']);

    $r = $s->run();

    expect($r['ok'])->toBeFalse()
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($r['steps'])->not->toContain('conversation_reply')
        ->and($s->bot->calls())->toBe([]);
});
