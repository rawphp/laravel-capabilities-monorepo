<?php

// Idempotent replays still spend D-013 turn budget and rate limits. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\RateLimitHelpers;

it('replay past the agent turn budget is rate_limited and keeps the stored result [D-013]', function () {
    $h = RateLimitHelpers::harness([
        'max_tool_calls' => 2,
        'per_min' => 1000,
        'per_cap' => 1000,
        'idempotent' => 'optional',
        'name' => 'replay-turn-budget',
    ]);
    $opts = fn (int $calls) => RateLimitHelpers::options('agent', [
        'idempotency_key' => 'loop-key-1',
        'agent_turn_tool_calls' => $calls,
    ]);

    $first = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts(1));
    $replay = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts(2));
    $looped = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts(3));
    $nextTurn = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts(1));

    expect($first->isOk())->toBeTrue()
        ->and($replay->isReplay())->toBeTrue()
        ->and($looped->errorCode())->toBe('rate_limited')
        ->and($looped->error['calls'] ?? null)->toBe(3)
        ->and($nextTurn->isReplay())->toBeTrue()
        ->and($nextTurn->data)->toEqual($first->data)
        ->and($h['runCount']->value)->toBe(1);
});

it('replay counts toward the per-capability rate limit [D-013]', function () {
    $h = RateLimitHelpers::harness([
        'per_min' => 1000,
        'per_cap' => 2,
        'idempotent' => 'optional',
        'name' => 'replay-per-cap',
    ]);
    $opts = RateLimitHelpers::options('http', ['idempotency_key' => 'hammer-key-1']);

    $first = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    $replay = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);
    $hammer = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);

    expect($first->isOk())->toBeTrue()
        ->and($replay->isReplay())->toBeTrue()
        ->and($hammer->errorCode())->toBe('rate_limited')
        ->and($h['runCount']->value)->toBe(1);
});
