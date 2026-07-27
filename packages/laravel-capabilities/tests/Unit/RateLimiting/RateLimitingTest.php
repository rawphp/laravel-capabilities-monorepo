<?php

// Spec-derived unit tests for D-013 rate limiting. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\RateLimiting\AgentTurnBudget;
use Rawphp\Capabilities\RateLimiting\RateLimitKey;
use Rawphp\Capabilities\Support\ErrorCodeMap;
use Rawphp\Capabilities\Tests\Fixtures\RateLimitHelpers;

it("happy: under limit invoke succeeds [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10]);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(1);
});

it("fail: exceeding per_minute returns rate_limited [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 1, 'per_cap' => 100, 'name' => 'rl-pm']);
    $opts = RateLimitHelpers::options();
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(1);
});

it("fail: exceeding per_capability_per_minute returns rate_limited [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 100, 'per_cap' => 1, 'name' => 'rl-pc']);
    $opts = RateLimitHelpers::options();
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(1);
});

it("happy: agent turn max_tool_calls stops loop with structured message [D-013]", function () {
    $h = RateLimitHelpers::harness(['max_tool_calls' => 2, 'per_min' => 1000, 'per_cap' => 1000]);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', [
        'agent_turn_tool_calls' => 3,
    ]));
    expect($r->errorCode())->toBe('rate_limited')
        ->and($r->error['structured'] ?? null)->not->toBeNull()
        ->and($r->error['max_tool_calls'] ?? null)->toBe(2)
        ->and($h['runCount']->value)->toBe(0);
});

it("edge: per-capability rateLimit attribute overrides defaults [D-013]", function () {
    $h = RateLimitHelpers::harness([
        'per_min' => 1000,
        'per_cap' => 1000,
        'rateLimit' => ['per_minute' => 1],
        'name' => 'rl-override',
    ]);
    $opts = RateLimitHelpers::options();
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(1);
});

it("happy: rate limit keys include actor capability surface tenant [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10]);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('http'));
    $key = $h['registry']->lastRateLimitKey();
    expect($key)->not->toBeNull()
        ->and(RateLimitKey::includesActor($key, 'user', '7'))->toBeTrue()
        ->and(RateLimitKey::includesCapability($key, $h['name']))->toBeTrue()
        ->and(RateLimitKey::includesSurface($key, 'http'))->toBeTrue()
        ->and(RateLimitKey::includesTenant($key, 't-1'))->toBeTrue();
});

it("edge: rate limits disabled when config enabled false [D-013]", function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1, 'per_cap' => 1]);
    $opts = RateLimitHelpers::options();
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    expect($r->isOk())->toBeTrue()->and($h['runCount']->value)->toBe(2);
});

foreach (['agent', 'mcp', 'http', 'cli', 'job'] as $caller) {
    it("edge: rate limit applies to caller {$caller} [D-013]", function () use ($caller) {
        $h = RateLimitHelpers::harness(['per_min' => 5, 'per_cap' => 5, 'name' => "rl-applies-{$caller}"]);
        $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options($caller));
        expect($r->isOk())->toBeTrue()
            ->and($h['registry']->lastRateLimitKey())->not->toBeNull()
            ->and(RateLimitKey::includesSurface((string) $h['registry']->lastRateLimitKey(), $caller))->toBeTrue();
    });

    it("fail: exceeding limit for caller {$caller} does not call run [D-013]", function () use ($caller) {
        $h = RateLimitHelpers::harness(['per_min' => 1, 'per_cap' => 1, 'name' => "rl-exceed-{$caller}"]);
        $opts = RateLimitHelpers::options($caller);
        $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
        $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
        expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(1);
    });
}

it("happy: rate_limited maps to HTTP 429 and CLI exit 6 [D-018]", function () {
    expect(ErrorCodeMap::httpStatus('rate_limited'))->toBe(429)
        ->and(ErrorCodeMap::cliExit('rate_limited'))->toBe(6);
    $h = RateLimitHelpers::harness(['per_min' => 0, 'per_cap' => 0]);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')
        ->and($r->error['http_status'] ?? null)->toBe(429)
        ->and($r->error['cli_exit'] ?? null)->toBe(6);
});
