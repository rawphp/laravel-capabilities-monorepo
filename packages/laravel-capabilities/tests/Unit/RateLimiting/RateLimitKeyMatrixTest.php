<?php

// Spec-derived unit tests for D-013 rate limit key matrix. Unit-only, no database.

declare(strict_types=1);

use Rawphp\Capabilities\RateLimiting\RateLimitKey;
use Rawphp\Capabilities\Tests\Fixtures\RateLimitHelpers;

it("edge: rate limit key includes actor for caller agent [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-agent-actor']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesActor($key, 'user', '7') || RateLimitKey::includesActor($key, 'system', 'billing-worker'))->toBeTrue();
});

it("edge: rate limit key includes capability for caller agent [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-agent-capability']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesCapability($key, $h['name']))->toBeTrue();
});

it("edge: rate limit key includes surface for caller agent [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-agent-surface']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesSurface($key, 'agent'))->toBeTrue();
});

it("edge: rate limit key includes tenant for caller agent [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-agent-tenant']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('agent'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesTenant($key, 't-1'))->toBeTrue();
});

it("edge: rate limit key includes actor for caller mcp [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-mcp-actor']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('mcp'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesActor($key, 'user', '7') || RateLimitKey::includesActor($key, 'system', 'billing-worker'))->toBeTrue();
});

it("edge: rate limit key includes capability for caller mcp [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-mcp-capability']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('mcp'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesCapability($key, $h['name']))->toBeTrue();
});

it("edge: rate limit key includes surface for caller mcp [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-mcp-surface']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('mcp'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesSurface($key, 'mcp'))->toBeTrue();
});

it("edge: rate limit key includes tenant for caller mcp [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-mcp-tenant']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('mcp'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesTenant($key, 't-1'))->toBeTrue();
});

it("edge: rate limit key includes actor for caller http [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-http-actor']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('http'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesActor($key, 'user', '7') || RateLimitKey::includesActor($key, 'system', 'billing-worker'))->toBeTrue();
});

it("edge: rate limit key includes capability for caller http [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-http-capability']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('http'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesCapability($key, $h['name']))->toBeTrue();
});

it("edge: rate limit key includes surface for caller http [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-http-surface']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('http'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesSurface($key, 'http'))->toBeTrue();
});

it("edge: rate limit key includes tenant for caller http [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-http-tenant']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('http'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesTenant($key, 't-1'))->toBeTrue();
});

it("edge: rate limit key includes actor for caller cli [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-cli-actor']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('cli'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesActor($key, 'user', '7') || RateLimitKey::includesActor($key, 'system', 'billing-worker'))->toBeTrue();
});

it("edge: rate limit key includes capability for caller cli [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-cli-capability']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('cli'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesCapability($key, $h['name']))->toBeTrue();
});

it("edge: rate limit key includes surface for caller cli [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-cli-surface']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('cli'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesSurface($key, 'cli'))->toBeTrue();
});

it("edge: rate limit key includes tenant for caller cli [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-cli-tenant']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('cli'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesTenant($key, 't-1'))->toBeTrue();
});

it("edge: rate limit key includes actor for caller job [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-job-actor']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('job'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesActor($key, 'user', '7') || RateLimitKey::includesActor($key, 'system', 'billing-worker'))->toBeTrue();
});

it("edge: rate limit key includes capability for caller job [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-job-capability']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('job'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesCapability($key, $h['name']))->toBeTrue();
});

it("edge: rate limit key includes surface for caller job [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-job-surface']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('job'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesSurface($key, 'job'))->toBeTrue();
});

it("edge: rate limit key includes tenant for caller job [D-013]", function () {
    $h = RateLimitHelpers::harness(['per_min' => 10, 'per_cap' => 10, 'name' => 'key-job-tenant']);
    $h['registry']->invoke($h['name'], RateLimitHelpers::input(), RateLimitHelpers::options('job'));
    $key = (string) $h['registry']->lastRateLimitKey();
    expect(RateLimitKey::includesTenant($key, 't-1'))->toBeTrue();
});

