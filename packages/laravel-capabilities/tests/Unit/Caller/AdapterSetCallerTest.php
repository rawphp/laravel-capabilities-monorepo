<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('happy: AiToolAdapter sets caller agent in server code [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['adapter' => 'agent', 'server_caller' => 'agent']))->toBe('agent');
});

it('fail: AiToolAdapter does not trust client caller field [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['adapter' => 'cli', 'server_caller' => 'cli']))->toBe('cli');
});

it('happy: McpToolAdapter sets caller mcp in server code [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['adapter' => 'mcp', 'server_caller' => 'mcp']))->toBe('mcp');
});

it('fail: McpToolAdapter does not trust client caller field [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['adapter' => 'cli', 'server_caller' => 'cli']))->toBe('cli');
});

it('happy: HttpController sets caller http_or_cli_derived in server code [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: HttpController does not trust client caller field [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('happy: RunCapabilityJob sets caller job in server code [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: RunCapabilityJob does not trust client caller field [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('happy: ArtisanCommand sets caller job_or_explicit in server code [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: ArtisanCommand does not trust client caller field [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('happy: InProcessInvoke sets caller explicit_argument in server code [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: InProcessInvoke does not trust client caller field [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});
