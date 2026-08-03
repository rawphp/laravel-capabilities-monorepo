<?php

// Spec-derived unit tests for D-013 agent turn budget matrix. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\Tests\Fixtures\RateLimitHelpers;

it('happy: agent loop allows when budget=1 calls=0 [D-013]', function () {
    $budget = new AgentTurnBudget(1);
    expect($budget->allows(0))->toBeTrue();
    expect($budget->exhausted(0))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 1, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-1-0']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 0]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=1 calls=1 [D-013]', function () {
    $budget = new AgentTurnBudget(1);
    expect($budget->allows(1))->toBeTrue();
    expect($budget->exhausted(1))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 1, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-1-1']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 1]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=1 calls=1 (case 1) [D-013]', function () {
    $budget = new AgentTurnBudget(1);
    expect($budget->allows(1))->toBeTrue();
    expect($budget->exhausted(1))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 1, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-1-1']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 1]));
    expect($r->isOk())->toBeTrue();
});

it('fail: agent loop stops when budget=1 calls=2 [D-013]', function () {
    $budget = new AgentTurnBudget(1);
    expect($budget->exhausted(2))->toBeTrue();
    expect($budget->allows(2))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 1, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-1-2']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 2]));
    expect($r->errorCode())->toBe('rate_limited')->and($r->error['structured'] ?? null)->not->toBeNull();
});

it('happy: agent loop allows when budget=2 calls=0 [D-013]', function () {
    $budget = new AgentTurnBudget(2);
    expect($budget->allows(0))->toBeTrue();
    expect($budget->exhausted(0))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 2, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-2-0']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 0]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=2 calls=1 [D-013]', function () {
    $budget = new AgentTurnBudget(2);
    expect($budget->allows(1))->toBeTrue();
    expect($budget->exhausted(1))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 2, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-2-1']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 1]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=2 calls=2 [D-013]', function () {
    $budget = new AgentTurnBudget(2);
    expect($budget->allows(2))->toBeTrue();
    expect($budget->exhausted(2))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 2, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-2-2']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 2]));
    expect($r->isOk())->toBeTrue();
});

it('fail: agent loop stops when budget=2 calls=3 [D-013]', function () {
    $budget = new AgentTurnBudget(2);
    expect($budget->exhausted(3))->toBeTrue();
    expect($budget->allows(3))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 2, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-2-3']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 3]));
    expect($r->errorCode())->toBe('rate_limited')->and($r->error['structured'] ?? null)->not->toBeNull();
});

it('happy: agent loop allows when budget=8 calls=0 [D-013]', function () {
    $budget = new AgentTurnBudget(8);
    expect($budget->allows(0))->toBeTrue();
    expect($budget->exhausted(0))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 8, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-8-0']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 0]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=8 calls=1 [D-013]', function () {
    $budget = new AgentTurnBudget(8);
    expect($budget->allows(1))->toBeTrue();
    expect($budget->exhausted(1))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 8, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-8-1']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 1]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=8 calls=8 [D-013]', function () {
    $budget = new AgentTurnBudget(8);
    expect($budget->allows(8))->toBeTrue();
    expect($budget->exhausted(8))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 8, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-8-8']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 8]));
    expect($r->isOk())->toBeTrue();
});

it('fail: agent loop stops when budget=8 calls=9 [D-013]', function () {
    $budget = new AgentTurnBudget(8);
    expect($budget->exhausted(9))->toBeTrue();
    expect($budget->allows(9))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 8, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-8-9']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 9]));
    expect($r->errorCode())->toBe('rate_limited')->and($r->error['structured'] ?? null)->not->toBeNull();
});

it('happy: agent loop allows when budget=16 calls=0 [D-013]', function () {
    $budget = new AgentTurnBudget(16);
    expect($budget->allows(0))->toBeTrue();
    expect($budget->exhausted(0))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 16, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-16-0']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 0]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=16 calls=1 [D-013]', function () {
    $budget = new AgentTurnBudget(16);
    expect($budget->allows(1))->toBeTrue();
    expect($budget->exhausted(1))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 16, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-16-1']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 1]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=16 calls=16 [D-013]', function () {
    $budget = new AgentTurnBudget(16);
    expect($budget->allows(16))->toBeTrue();
    expect($budget->exhausted(16))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 16, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-16-16']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 16]));
    expect($r->isOk())->toBeTrue();
});

it('fail: agent loop stops when budget=16 calls=17 [D-013]', function () {
    $budget = new AgentTurnBudget(16);
    expect($budget->exhausted(17))->toBeTrue();
    expect($budget->allows(17))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 16, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-16-17']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 17]));
    expect($r->errorCode())->toBe('rate_limited')->and($r->error['structured'] ?? null)->not->toBeNull();
});

it('happy: agent loop allows when budget=32 calls=0 [D-013]', function () {
    $budget = new AgentTurnBudget(32);
    expect($budget->allows(0))->toBeTrue();
    expect($budget->exhausted(0))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 32, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-32-0']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 0]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=32 calls=1 [D-013]', function () {
    $budget = new AgentTurnBudget(32);
    expect($budget->allows(1))->toBeTrue();
    expect($budget->exhausted(1))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 32, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-32-1']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 1]));
    expect($r->isOk())->toBeTrue();
});

it('happy: agent loop allows when budget=32 calls=32 [D-013]', function () {
    $budget = new AgentTurnBudget(32);
    expect($budget->allows(32))->toBeTrue();
    expect($budget->exhausted(32))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 32, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-ok-32-32']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 32]));
    expect($r->isOk())->toBeTrue();
});

it('fail: agent loop stops when budget=32 calls=33 [D-013]', function () {
    $budget = new AgentTurnBudget(32);
    expect($budget->exhausted(33))->toBeTrue();
    expect($budget->allows(33))->toBeFalse();
    $h = RateLimitHelpers::harness(['max_tool_calls' => 32, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'turn-32-33']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 33]));
    expect($r->errorCode())->toBe('rate_limited')->and($r->error['structured'] ?? null)->not->toBeNull();
});
