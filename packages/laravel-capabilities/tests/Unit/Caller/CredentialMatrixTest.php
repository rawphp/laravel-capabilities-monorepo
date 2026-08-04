<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it("happy: token ability 'capabilities:cli' derives caller cli [D-022]", function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['capabilities:cli']]))->toBe('cli');
});

it("happy: token ability 'none' derives caller http [D-022]", function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("happy: token ability 'api' derives caller http [D-022]", function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it("happy: token ability 'capabilities:http' derives caller http [D-022]", function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('edge: oauth client_id capabilities-cli maps to cli when configured [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: oauth client_id ios-app maps to http when configured [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('edge: oauth client_id unknown-client maps to http when configured [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: header claim cli with derived http results in ignored_or_rejected_upgrade [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('fail: header claim http with derived cli results in downgrade_or_keep [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('fail: header claim agent with derived http results in ignored_upgrade [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});

it('fail: header claim job with derived http results in ignored_upgrade [D-022]', function () {
    $d = H::defaultDeriver();
    expect($d->deriveFromCredential(['token_abilities' => ['*']]))->toBe('http');
});
