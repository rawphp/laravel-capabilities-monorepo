<?php

// Per-capability agent turn budget override (D-013). Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\Tests\Fixtures\RateLimitHelpers;

it('narrowedTo keeps the tighter max and never loosens [D-013]', function () {
    $budget = new AgentTurnBudget(16);

    expect($budget->narrowedTo(3)->max())->toBe(3)
        ->and($budget->narrowedTo(40)->max())->toBe(16)
        ->and($budget->narrowedTo(null))->toBe($budget)
        ->and($budget->narrowedTo(-5)->max())->toBe(0);
});

it('capability max_tool_calls_per_turn stops the loop before the global budget [D-013]', function () {
    $h = RateLimitHelpers::harness([
        'max_tool_calls' => 16,
        'per_min' => 1000,
        'per_cap' => 1000,
        'rateLimit' => ['max_tool_calls_per_turn' => 2],
        'name' => 'turn-cap-tight',
    ]);

    $ok = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 2]));
    $stopped = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 3]));

    expect($ok->isOk())->toBeTrue()
        ->and($stopped->errorCode())->toBe('rate_limited')
        ->and($stopped->error['max_tool_calls'] ?? null)->toBe(2)
        ->and($stopped->error['calls'] ?? null)->toBe(3)
        ->and($h['runCount']->value)->toBe(1);
});

it('capability max_tool_calls_per_turn cannot loosen the global budget [D-013]', function () {
    $h = RateLimitHelpers::harness([
        'max_tool_calls' => 2,
        'per_min' => 1000,
        'per_cap' => 1000,
        'rateLimit' => ['max_tool_calls_per_turn' => 50],
        'name' => 'turn-cap-loose',
    ]);

    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 3]));

    expect($r->errorCode())->toBe('rate_limited')
        ->and($r->error['max_tool_calls'] ?? null)->toBe(2)
        ->and($h['runCount']->value)->toBe(0);
});

it('turn budget applies to job-caller turns that report a tool-call count [D-013]', function () {
    $h = RateLimitHelpers::harness([
        'per_min' => 1000,
        'per_cap' => 1000,
        'rateLimit' => ['max_tool_calls_per_turn' => 1],
        'name' => 'turn-cap-job',
    ]);

    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('job', ['agent_turn_tool_calls' => 2]));

    expect($r->errorCode())->toBe('rate_limited')
        ->and($h['runCount']->value)->toBe(0);
});

it('invokes without a turn count ignore max_tool_calls_per_turn [D-013]', function () {
    $h = RateLimitHelpers::harness([
        'per_min' => 1000,
        'per_cap' => 1000,
        'rateLimit' => ['max_tool_calls_per_turn' => 0],
        'name' => 'turn-cap-no-count',
    ]);

    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('http'));

    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});
