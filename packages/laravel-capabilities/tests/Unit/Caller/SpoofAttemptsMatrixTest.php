<?php

declare(strict_types=1);

use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('fail: client-provided caller is not authoritative for caller agent [D-022]', function () {
    $d = H::defaultDeriver();
    // Server sets caller=agent; client body/header claim cannot replace
    $server = $d->deriveFromCredential(['server_caller' => 'agent']);
    expect($server)->toBe('agent');
    expect($d->fromClientBody(['caller' => 'http']))->toBeNull();
    $r = $d->applyHeaderClaim('agent', 'http');
    if ($d->isMorePrivileged('http', 'agent')) {
        expect($r['caller'])->toBe('agent');
    }
});

it('fail: client-provided caller is not authoritative for caller mcp [D-022]', function () {
    $d = H::defaultDeriver();
    // Server sets caller=mcp; client body/header claim cannot replace
    $server = $d->deriveFromCredential(['server_caller' => 'mcp']);
    expect($server)->toBe('mcp');
    expect($d->fromClientBody(['caller' => 'http']))->toBeNull();
    $r = $d->applyHeaderClaim('mcp', 'http');
    if ($d->isMorePrivileged('http', 'mcp')) {
        expect($r['caller'])->toBe('mcp');
    }
});

it('fail: client-provided caller is not authoritative for caller http [D-022]', function () {
    $d = H::defaultDeriver();
    // Server sets caller=http; client body/header claim cannot replace
    $server = $d->deriveFromCredential(['server_caller' => 'http']);
    expect($server)->toBe('http');
    expect($d->fromClientBody(['caller' => 'http']))->toBeNull();
    $r = $d->applyHeaderClaim('http', 'http');
    if ($d->isMorePrivileged('http', 'http')) {
        expect($r['caller'])->toBe('http');
    }
});

it('fail: client-provided caller is not authoritative for caller cli [D-022]', function () {
    $d = H::defaultDeriver();
    // Server sets caller=cli; client body/header claim cannot replace
    $server = $d->deriveFromCredential(['server_caller' => 'cli']);
    expect($server)->toBe('cli');
    expect($d->fromClientBody(['caller' => 'http']))->toBeNull();
    $r = $d->applyHeaderClaim('cli', 'http');
    if ($d->isMorePrivileged('http', 'cli')) {
        expect($r['caller'])->toBe('cli');
    }
});

it('fail: client-provided caller is not authoritative for caller job [D-022]', function () {
    $d = H::defaultDeriver();
    // Server sets caller=job; client body/header claim cannot replace
    $server = $d->deriveFromCredential(['server_caller' => 'job']);
    expect($server)->toBe('job');
    expect($d->fromClientBody(['caller' => 'http']))->toBeNull();
    $r = $d->applyHeaderClaim('job', 'http');
    if ($d->isMorePrivileged('http', 'job')) {
        expect($r['caller'])->toBe('job');
    }
});

it('fail: client-provided actor is not authoritative for caller agent [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('agent');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('agent');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor is not authoritative for caller mcp [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('mcp');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('mcp');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor is not authoritative for caller http [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('http');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('http');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor is not authoritative for caller cli [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('cli');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('cli');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor is not authoritative for caller job [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('job');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('job');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided user_id is not authoritative for caller agent [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('agent');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('agent');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided user_id is not authoritative for caller mcp [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('mcp');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('mcp');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided user_id is not authoritative for caller http [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('http');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('http');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided user_id is not authoritative for caller cli [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('cli');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('cli');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided user_id is not authoritative for caller job [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('job');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('job');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor_id is not authoritative for caller agent [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('agent');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('agent');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor_id is not authoritative for caller mcp [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('mcp');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('mcp');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor_id is not authoritative for caller http [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('http');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('http');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor_id is not authoritative for caller cli [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('cli');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('cli');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided actor_id is not authoritative for caller job [D-022]', function () {
    // Ambient identity from client body is untrusted; actor must be server-derived
    $h = PipelineHelpers::harness(['allowSystemCallers' => true]);
    $opts = PipelineHelpers::options('job');
    // Inject client-claimed identity fields — must not become authority
    $opts['client_claims'] = ['actor' => 'evil', 'user_id' => 999, 'actor_id' => 'evil'];
    $result = $h['registry']->invoke($h['name'], PipelineHelpers::validInput(), $opts);
    expect($result->isOk() || $result->errorCode() !== null)->toBeTrue();
    $ctx = $h['registry']->lastState()?->context;
    if ($ctx) {
        expect($ctx->caller())->toBe('job');
        // actor remains the server-provided principal, not evil string
        expect($ctx->actor())->toBeObject();
    }
});

it('fail: client-provided tenant_id is not authoritative for caller agent [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided tenant_id is not authoritative for caller mcp [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided tenant_id is not authoritative for caller http [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided tenant_id is not authoritative for caller cli [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided tenant_id is not authoritative for caller job [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided auth_profile is not authoritative for caller agent [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided auth_profile is not authoritative for caller mcp [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided auth_profile is not authoritative for caller http [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided auth_profile is not authoritative for caller cli [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided auth_profile is not authoritative for caller job [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided client_id is not authoritative for caller agent [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided client_id is not authoritative for caller mcp [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided client_id is not authoritative for caller http [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided client_id is not authoritative for caller cli [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});

it('fail: client-provided client_id is not authoritative for caller job [D-022]', function () {
    $d = H::defaultDeriver();
    $r = $d->resolve(['token_abilities' => ['capabilities:cli']], 'http');
    expect($r['derived'])->toBe('cli');
    expect($r['caller'])->toBe('cli');
});
