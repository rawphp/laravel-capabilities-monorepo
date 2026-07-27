<?php

// REQ-010 fleshed unit tests for Profiles/MaxToolsMatrixTest.php. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Profiles\ProfileRequiredException;
use Rawphp\Capabilities\Profiles\TooManyToolsException;
use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\Tests\Fixtures\ProfileHelpers;

it("happy: tool count 0 accepted [D-008]", function () {
    $h = ProfileHelpers::multiCapHarness(['caps' => []]);
    $tools = $h['registry']->aiTools(['only' => ['no-such']]);
    expect($tools)->toBeEmpty();
});

it("happy: tool count 1 accepted [D-008]", function () {
    $names = array_map(fn ($i) => 'tool-'.$i, range(0, 1-1));
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => $names],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 1);
    $tools = $h['registry']->aiTools('bulk');
    expect(count($tools))->toBe(1);
});

it("happy: tool count 32 accepted [D-008]", function () {
    $names = array_map(fn ($i) => 'tool-'.$i, range(0, 32-1));
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => $names],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 32);
    $tools = $h['registry']->aiTools('bulk');
    expect(count($tools))->toBe(32);
});

it("edge: tool count 33 warns above 32 [D-008]", function () {
    $names = array_map(fn ($i) => 'tool-'.$i, range(0, 33-1));
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => $names],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 33);
    $tools = $h['registry']->aiTools('bulk');
    expect(count($tools))->toBe(33);
    expect($h['registry']->logs())->not->toBeEmpty();
});

it("edge: tool count 64 warns above 32 [D-008]", function () {
    $names = array_map(fn ($i) => 'tool-'.$i, range(0, 64-1));
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => $names],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 64);
    $tools = $h['registry']->aiTools('bulk');
    expect(count($tools))->toBe(64);
    expect($h['registry']->logs())->not->toBeEmpty();
});

it("fail: tool count 65 exceeds hard max 64 [D-008]", function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => array_map(fn ($i) => 'tool-'.$i, range(0, 65-1))],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 65);
    expect(fn () => $h['registry']->aiTools('bulk'))
        ->toThrow(TooManyToolsException::class);
});

it("fail: tool count 100 exceeds hard max 64 [D-008]", function () {
    $h = ProfileHelpers::multiCapHarness([
        'caps' => [],
        'tool_surface' => [
            'agent' => [
                'profiles' => ['bulk' => array_map(fn ($i) => 'tool-'.$i, range(0, 100-1))],
                'require_profile' => true,
                'max_tools_warn' => 32,
                'max_tools_hard' => 64,
            ],
        ],
    ]);
    ProfileHelpers::registerN($h['registry'], 100);
    expect(fn () => $h['registry']->aiTools('bulk'))
        ->toThrow(TooManyToolsException::class);
});

it("edge: agent max_tool_calls_per_turn=1 enforced [D-013]", function () {
    $budget = new AgentTurnBudget(1);
    expect($budget->allows(1))->toBeTrue()
        ->and($budget->exhausted(1))->toBeFalse()
        ->and($budget->max())->toBe(1);
});

it("fail: agent exceeding max_tool_calls_per_turn=1 stops loop [D-013]", function () {
    $budget = new AgentTurnBudget(1);
    expect($budget->exhausted(1 + 1))->toBeTrue()
        ->and($budget->stopMessage(1 + 1)['code'])->toBe('rate_limited');
});

it("edge: agent max_tool_calls_per_turn=8 enforced [D-013]", function () {
    $budget = new AgentTurnBudget(8);
    expect($budget->allows(8))->toBeTrue()
        ->and($budget->exhausted(8))->toBeFalse()
        ->and($budget->max())->toBe(8);
});

it("fail: agent exceeding max_tool_calls_per_turn=8 stops loop [D-013]", function () {
    $budget = new AgentTurnBudget(8);
    expect($budget->exhausted(8 + 1))->toBeTrue()
        ->and($budget->stopMessage(8 + 1)['code'])->toBe('rate_limited');
});

it("edge: agent max_tool_calls_per_turn=16 enforced [D-013]", function () {
    $budget = new AgentTurnBudget(16);
    expect($budget->allows(16))->toBeTrue()
        ->and($budget->exhausted(16))->toBeFalse()
        ->and($budget->max())->toBe(16);
});

it("fail: agent exceeding max_tool_calls_per_turn=16 stops loop [D-013]", function () {
    $budget = new AgentTurnBudget(16);
    expect($budget->exhausted(16 + 1))->toBeTrue()
        ->and($budget->stopMessage(16 + 1)['code'])->toBe('rate_limited');
});

it("edge: agent max_tool_calls_per_turn=32 enforced [D-013]", function () {
    $budget = new AgentTurnBudget(32);
    expect($budget->allows(32))->toBeTrue()
        ->and($budget->exhausted(32))->toBeFalse()
        ->and($budget->max())->toBe(32);
});

it("fail: agent exceeding max_tool_calls_per_turn=32 stops loop [D-013]", function () {
    $budget = new AgentTurnBudget(32);
    expect($budget->exhausted(32 + 1))->toBeTrue()
        ->and($budget->stopMessage(32 + 1)['code'])->toBe('rate_limited');
});
