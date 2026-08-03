<?php

declare(strict_types=1);

use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('happy: context field caller accessible when set [CTX-001]', function () {
    $ctx = H::context(caller: 'cli');
    expect($ctx->caller())->toBe('cli');
});

it('edge: context field caller null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect(fn () => $ctx->caller())->not->toThrow(Throwable::class);
});

it('happy: context field actor accessible when set [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->actor())->toBeObject();
});

it('edge: context field actor null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect(fn () => $ctx->actor())->not->toThrow(Throwable::class);
});

it('happy: context field user accessible when set [CTX-001]', function () {
    $ctx = H::context(actor: H::user(9));
    expect($ctx->user())->not->toBeNull()->and($ctx->user()->id)->toBe(9);
});

it('edge: context field user null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context(caller: 'job', actor: H::system());
    expect($ctx->user())->toBeNull();
});

it('happy: context field scope accessible when set [CTX-001]', function () {
    $ctx = H::context(scope: new CapabilityScope(tenantId: 't'));
    expect($ctx->scope())->toBeInstanceOf(CapabilityScope::class);
});

it('edge: context field scope null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->scope())->toBeNull();
});

it('happy: context field tenantId accessible when set [CTX-001]', function () {
    $scope = new CapabilityScope(tenantId: 't1', teamId: 'team-1', organizationId: 'org-1');
    $ctx = H::context(scope: $scope);
    expect($ctx->tenantId())->toBe('t1');
});

it('edge: context field tenantId null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->tenantId())->toBeNull();
});

it('happy: context field teamId accessible when set [CTX-001]', function () {
    $scope = new CapabilityScope(tenantId: 't1', teamId: 'team-1', organizationId: 'org-1');
    $ctx = H::context(scope: $scope);
    expect($ctx->teamId())->toBe('team-1');
});

it('edge: context field teamId null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->teamId())->toBeNull();
});

it('happy: context field organizationId accessible when set [CTX-001]', function () {
    $scope = new CapabilityScope(tenantId: 't1', teamId: 'team-1', organizationId: 'org-1');
    $ctx = H::context(scope: $scope);
    expect($ctx->organizationId())->toBe('org-1');
});

it('edge: context field organizationId null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->organizationId())->toBeNull();
});

it('happy: context field requestId accessible when set [CTX-001]', function () {
    $ctx = H::context(extra: ['request_id' => 'req-1']);
    expect($ctx->requestId())->toBe('req-1');
});

it('edge: context field requestId null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->requestId())->toBeNull();
});

it('happy: context field traceId accessible when set [CTX-001]', function () {
    $ctx = H::context(extra: ['trace_id' => 'tr-1']);
    expect($ctx->traceId())->toBe('tr-1');
});

it('edge: context field traceId null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->traceId())->toBeNull();
});

it('happy: context field agent accessible when set [CTX-001]', function () {
    $ctx = H::context(caller: 'agent', extra: ['agent' => ['turn_id' => 't1']]);
    expect($ctx->agent())->toBeArray()->and($ctx->agent()['turn_id'])->toBe('t1');
});

it('edge: context field agent null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->agent())->toBeNull();
});

it('happy: context field mcp accessible when set [CTX-001]', function () {
    $ctx = H::context(caller: 'mcp', extra: ['mcp' => ['auth_profile' => 'user_pat', 'client_id' => 'c1']]);
    expect($ctx->mcp())->toBeArray()->and($ctx->mcp()['auth_profile'])->toBe('user_pat');
});

it('edge: context field mcp null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->mcp())->toBeNull();
});

it('happy: context field messaging accessible when set [CTX-001]', function () {
    $ctx = H::context(caller: 'agent', extra: ['messaging' => ['channel' => 'telegram']]);
    expect($ctx->messaging())->toBeArray()->and($ctx->messaging()['channel'])->toBe('telegram');
});

it('edge: context field messaging null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->messaging())->toBeNull();
});

it('happy: context field job accessible when set [CTX-001]', function () {
    $ctx = H::context(caller: 'job', actor: H::system(), extra: ['job' => ['tenant_id' => 't7']]);
    expect($ctx->job())->toBeArray()->and($ctx->jobTenantId())->toBe('t7');
});

it('edge: context field job null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->job())->toBeNull();
});

it('happy: context field credential accessible when set [CTX-001]', function () {
    $ctx = H::context(extra: ['credential' => ['type' => 'sanctum', 'ability' => 'capabilities:cli']]);
    expect($ctx->credential())->toBeArray()->and($ctx->credential()['type'])->toBe('sanctum');
});

it('edge: context field credential null-safe when unset if optional [CTX-001]', function () {
    $ctx = H::context();
    expect($ctx->credential())->toBeNull();
});
