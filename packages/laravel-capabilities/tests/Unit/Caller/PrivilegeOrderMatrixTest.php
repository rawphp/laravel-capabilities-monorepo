<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('edge: header http matching derived http is no-op [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('edge: header downgrade derived http claim cli allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('edge: header downgrade derived http claim mcp allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('edge: header downgrade derived http claim agent allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('edge: header downgrade derived http claim job allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('fail: header upgrade derived cli claim http ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('edge: header cli matching derived cli is no-op [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header downgrade derived cli claim mcp allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header downgrade derived cli claim agent allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header downgrade derived cli claim job allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: header upgrade derived mcp claim http ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('fail: header upgrade derived mcp claim cli ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header mcp matching derived mcp is no-op [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header downgrade derived mcp claim agent allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header downgrade derived mcp claim job allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: header upgrade derived agent claim http ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('fail: header upgrade derived agent claim cli ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: header upgrade derived agent claim mcp ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header agent matching derived agent is no-op [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header downgrade derived agent claim job allowed per policy [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: header upgrade derived job claim http ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('fail: header upgrade derived job claim cli ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: header upgrade derived job claim mcp ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: header upgrade derived job claim agent ignored or rejected [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: header job matching derived job is no-op [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});
