<?php

// Spec-derived unit tests for D-013 config matrix. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\RateLimitHelpers;

it('edge: zero limit edge when enabled=True per_min=0 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 0, 'per_cap' => 0, 'name' => 'cfg1']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(0);
});

it('edge: zero limit edge when enabled=True per_min=0 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 0, 'per_cap' => 1, 'name' => 'cfg2']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(0);
});

it('edge: zero limit edge when enabled=True per_min=0 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 0, 'per_cap' => 10, 'name' => 'cfg3']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(0);
});

it('edge: zero limit edge when enabled=True per_min=0 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 0, 'per_cap' => 30, 'name' => 'cfg4']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(0);
});

it('edge: zero limit edge when enabled=True per_min=1 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 1, 'per_cap' => 0, 'name' => 'cfg5']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(0);
});

it('happy: limits enforced when enabled=True per_min=1 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 1, 'per_cap' => 1, 'name' => 'cfg6']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
    $r2 = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r2->errorCode())->toBe('rate_limited');
});

it('happy: limits enforced when enabled=True per_min=1 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 1, 'per_cap' => 10, 'name' => 'cfg7']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
    $r2 = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r2->errorCode())->toBe('rate_limited');
});

it('happy: limits enforced when enabled=True per_min=1 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 1, 'per_cap' => 30, 'name' => 'cfg8']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
    $r2 = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r2->errorCode())->toBe('rate_limited');
});

it('edge: zero limit edge when enabled=True per_min=30 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 30, 'per_cap' => 0, 'name' => 'cfg9']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(0);
});

it('happy: limits enforced when enabled=True per_min=30 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 30, 'per_cap' => 1, 'name' => 'cfg10']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
    $r2 = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r2->errorCode())->toBe('rate_limited');
});

it('happy: limits enforced when enabled=True per_min=30 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 30, 'per_cap' => 10, 'name' => 'cfg11']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
});

it('happy: limits enforced when enabled=True per_min=30 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 30, 'per_cap' => 30, 'name' => 'cfg12']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
});

it('edge: zero limit edge when enabled=True per_min=60 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 60, 'per_cap' => 0, 'name' => 'cfg13']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(0);
});

it('happy: limits enforced when enabled=True per_min=60 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 60, 'per_cap' => 1, 'name' => 'cfg14']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
    $r2 = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r2->errorCode())->toBe('rate_limited');
});

it('happy: limits enforced when enabled=True per_min=60 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 60, 'per_cap' => 10, 'name' => 'cfg15']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
});

it('happy: limits enforced when enabled=True per_min=60 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 60, 'per_cap' => 30, 'name' => 'cfg16']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
});

it('edge: zero limit edge when enabled=True per_min=1000 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 1000, 'per_cap' => 0, 'name' => 'cfg17']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->errorCode())->toBe('rate_limited')->and($h['runCount']->value)->toBe(0);
});

it('happy: limits enforced when enabled=True per_min=1000 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 1000, 'per_cap' => 1, 'name' => 'cfg18']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
    $r2 = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r2->errorCode())->toBe('rate_limited');
});

it('happy: limits enforced when enabled=True per_min=1000 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 1000, 'per_cap' => 10, 'name' => 'cfg19']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
});

it('happy: limits enforced when enabled=True per_min=1000 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => true, 'per_min' => 1000, 'per_cap' => 30, 'name' => 'cfg20']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
    // second invoke within tight limit of 1 should fail when either dimension is 1
});

it('edge: rate limits off when enabled=False per_min=0 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 0, 'per_cap' => 0, 'name' => 'cfg21']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=0 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 0, 'per_cap' => 1, 'name' => 'cfg22']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=0 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 0, 'per_cap' => 10, 'name' => 'cfg23']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=0 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 0, 'per_cap' => 30, 'name' => 'cfg24']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=1 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1, 'per_cap' => 0, 'name' => 'cfg25']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=1 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1, 'per_cap' => 1, 'name' => 'cfg26']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=1 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1, 'per_cap' => 10, 'name' => 'cfg27']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=1 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1, 'per_cap' => 30, 'name' => 'cfg28']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=30 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 30, 'per_cap' => 0, 'name' => 'cfg29']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=30 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 30, 'per_cap' => 1, 'name' => 'cfg30']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=30 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 30, 'per_cap' => 10, 'name' => 'cfg31']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=30 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 30, 'per_cap' => 30, 'name' => 'cfg32']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=60 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 60, 'per_cap' => 0, 'name' => 'cfg33']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=60 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 60, 'per_cap' => 1, 'name' => 'cfg34']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=60 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 60, 'per_cap' => 10, 'name' => 'cfg35']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=60 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 60, 'per_cap' => 30, 'name' => 'cfg36']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=1000 per_cap=0 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1000, 'per_cap' => 0, 'name' => 'cfg37']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=1000 per_cap=1 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1000, 'per_cap' => 1, 'name' => 'cfg38']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=1000 per_cap=10 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1000, 'per_cap' => 10, 'name' => 'cfg39']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});

it('edge: rate limits off when enabled=False per_min=1000 per_cap=30 [D-013]', function () {
    $h = RateLimitHelpers::harness(['enabled' => false, 'per_min' => 1000, 'per_cap' => 30, 'name' => 'cfg40']);
    $r = $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options());
    expect($r->isOk())->toBeTrue();
});
