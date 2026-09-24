<?php

// D-013 rate limit key segments are escaped so distinct dimensions never share a bucket. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\RateLimiting\RateLimitKey;

it('fail: colon in actor id cannot collide actor surface buckets [D-013]', function () {
    $a = RateLimitKey::actorSurface('t-1', 'user', '7:http', 'mcp');
    $b = RateLimitKey::actorSurface('t-1', 'user', '7', 'http:mcp');

    expect($a)->not->toBe($b);
});

it('fail: colon in actor id or capability cannot collide capability buckets [D-013]', function () {
    $a = RateLimitKey::capability('t-1', 'user', '7:invoices.void', 'x', 'http');
    $b = RateLimitKey::capability('t-1', 'user', '7', 'invoices.void:x', 'http');

    expect($a)->not->toBe($b);
});

it('fail: colon in tenant cannot collide with another tenant and actor [D-013]', function () {
    $a = RateLimitKey::actorSurface('acme:user', '7', 'x', 'http');
    $b = RateLimitKey::actorSurface('acme', 'user', '7:x', 'http');

    expect($a)->not->toBe($b);
});

it('edge: null tenant does not share a bucket with tenant named none [D-013]', function () {
    expect(RateLimitKey::actorSurface(null, 'user', '7', 'http'))
        ->not->toBe(RateLimitKey::actorSurface('none', 'user', '7', 'http'))
        ->and(RateLimitKey::capability(null, 'user', '7', 'cap', 'http'))
        ->not->toBe(RateLimitKey::capability('none', 'user', '7', 'cap', 'http'));
});

it('happy: parts decodes escaped segments in order [D-013]', function () {
    $key = RateLimitKey::capability('acme:eu', 'user', '7:a%b', 'invoices:void', 'http');

    expect(RateLimitKey::parts($key))->toBe(['rl', 'cap', 'acme:eu', 'user', '7:a%b', 'invoices:void', 'http']);
});

it('happy: includes helpers match escaped dimension values [D-013]', function () {
    $key = RateLimitKey::capability('acme:eu', 'user', '7:a', 'invoices:void', 'http');

    expect(RateLimitKey::includesTenant($key, 'acme:eu'))->toBeTrue()
        ->and(RateLimitKey::includesActor($key, 'user', '7:a'))->toBeTrue()
        ->and(RateLimitKey::includesCapability($key, 'invoices:void'))->toBeTrue()
        ->and(RateLimitKey::includesSurface($key, 'http'))->toBeTrue()
        ->and(RateLimitKey::includesTenant($key, null))->toBeFalse()
        ->and(RateLimitKey::includesTenant(RateLimitKey::actorSurface(null, 'user', '7', 'http'), null))->toBeTrue();
});

it('edge: includes helpers match whole segments not substrings [D-013]', function () {
    $key = RateLimitKey::capability('t-10', 'user', '70', 'invoices.void', 'http');

    expect(RateLimitKey::includesTenant($key, 't-1'))->toBeFalse()
        ->and(RateLimitKey::includesActor($key, 'user', '7'))->toBeFalse()
        ->and(RateLimitKey::includesCapability($key, 'invoices'))->toBeFalse()
        ->and(RateLimitKey::includesSurface($key, 'htt'))->toBeFalse();
});
