<?php

// C-007 / D-013: a pipeline rate_limited envelope tells the client how long to wait
// (`error.retry_after` seconds) and the HTTP edge mirrors it as `Retry-After`, so the CLI's
// backoff hint is fed by the core limiter, not only by a proxy or throttle middleware.

declare(strict_types=1);

use Rawphp\Capabilities\Http\HttpResponse;
use Rawphp\Capabilities\Support\ArrayRateLimitCache;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\InMemoryRateLimiter;
use Rawphp\Capabilities\Support\LaravelCacheRateLimiter;
use Rawphp\Capabilities\Tests\Fixtures\RateLimitHelpers;

it('happy: InMemoryRateLimiter availableIn reports the remaining decay window and 0 when free [C-007]', function () {
    $limiter = new InMemoryRateLimiter;

    expect($limiter->availableIn('cold'))->toBe(0);

    $limiter->hit('warm', 45);
    expect($limiter->availableIn('warm'))->toBeGreaterThan(40)
        ->and($limiter->availableIn('warm'))->toBeLessThanOrEqual(45);

    $limiter->clear('warm');
    expect($limiter->availableIn('warm'))->toBe(0);
});

it('happy: LaravelCacheRateLimiter availableIn reads the window timer and 0 when free [C-007]', function () {
    $limiter = new LaravelCacheRateLimiter(new ArrayRateLimitCache);

    expect($limiter->availableIn('cold'))->toBe(0);

    $limiter->hit('warm', 30);
    expect($limiter->availableIn('warm'))->toBeGreaterThan(25)
        ->and($limiter->availableIn('warm'))->toBeLessThanOrEqual(30);

    $limiter->clear('warm');
    expect($limiter->availableIn('warm'))->toBe(0);
});

it('happy: a pipeline rate_limited failure carries retry_after seconds from the tripped key [C-007 / D-013]', function () {
    $h = RateLimitHelpers::harness(['per_min' => 1, 'per_cap' => 100, 'name' => 'rl-retry-after']);
    $opts = RateLimitHelpers::options();
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);

    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);

    expect($r->errorCode())->toBe('rate_limited')
        ->and($r->error['retryable'])->toBeTrue()
        ->and($r->error['retry_after'])->toBeInt()
        ->and($r->error['retry_after'])->toBeGreaterThan(0)
        ->and($r->error['retry_after'])->toBeLessThanOrEqual(60);
});

it('edge: a per-capability decay override shapes retry_after [C-007 / D-013]', function () {
    $h = RateLimitHelpers::harness(['per_min' => 100, 'per_cap' => 1, 'name' => 'rl-retry-decay', 'rateLimit' => ['decay' => 5]]);
    $opts = RateLimitHelpers::options();
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);

    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), $opts);

    expect($r->errorCode())->toBe('rate_limited')
        ->and($r->error['retry_after'])->toBeLessThanOrEqual(5)
        ->and($r->error['retry_after'])->toBeGreaterThan(0);
});

it('edge: zero limits and the agent turn budget send no retry_after (nothing to wait for) [C-007]', function () {
    $zero = RateLimitHelpers::harness(['per_min' => 0, 'per_cap' => 0, 'name' => 'rl-zero-retry']);
    $r = $zero['registry']->invoke($zero['name'], RateLimitHelpers::input(), RateLimitHelpers::options());

    $turn = RateLimitHelpers::harness(['max_tool_calls' => 1, 'per_min' => 1000, 'per_cap' => 1000, 'name' => 'rl-turn-retry']);
    $t = $turn['registry']->invoke($turn['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent', ['agent_turn_tool_calls' => 2]));

    expect($r->errorCode())->toBe('rate_limited')
        ->and($r->error)->not->toHaveKey('retry_after')
        ->and($t->errorCode())->toBe('rate_limited')
        ->and($t->error)->not->toHaveKey('retry_after');
});

it('happy: HttpResponse adds Retry-After on 429 when the envelope carries retry_after [C-007]', function () {
    $limited = CapabilityResult::failure('rate_limited', 'slow down', ['retry_after' => 17]);

    $plain = HttpResponse::fromResult($limited);
    $cli = HttpResponse::fromResult($limited, cliEnvelope: true, headers: ['X-Request-Id' => 'r-1']);

    expect($plain->status)->toBe(429)
        ->and($plain->headers['Retry-After'])->toBe('17')
        ->and($cli->headers)->toBe(['X-Request-Id' => 'r-1', 'Retry-After' => '17']);
});

it('edge: HttpResponse sends no Retry-After without a positive retry_after or off 429, and never overrides an explicit header [C-007]', function () {
    $noHint = HttpResponse::fromResult(CapabilityResult::failure('rate_limited', 'slow down'));
    $zero = HttpResponse::fromResult(CapabilityResult::failure('rate_limited', 'slow down', ['retry_after' => 0]));
    $other = HttpResponse::fromResult(CapabilityResult::failure('conflict', 'busy', ['retry_after' => 9]));
    $explicit = HttpResponse::fromResult(
        CapabilityResult::failure('rate_limited', 'slow down', ['retry_after' => 17]),
        headers: ['Retry-After' => '120'],
    );

    expect($noHint->headers)->not->toHaveKey('Retry-After')
        ->and($zero->headers)->not->toHaveKey('Retry-After')
        ->and($other->headers)->not->toHaveKey('Retry-After')
        ->and($explicit->headers['Retry-After'])->toBe('120');
});
