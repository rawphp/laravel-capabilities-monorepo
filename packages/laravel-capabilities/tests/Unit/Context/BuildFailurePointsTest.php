<?php

declare(strict_types=1);

use InvalidArgumentException;
use Rawphp\Capabilities\Adapters\RunCapabilityJob;
use Rawphp\Capabilities\Pipeline\ResolveTenantFromCaller;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Support\DefaultScopeResolver;
use Rawphp\Capabilities\Support\MissingJobActorException;
use Rawphp\Capabilities\Support\UnresolvedScopeException;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('fail: context build refuses null_actor [CTX-001]', function () {
    expect(fn () => CapabilityContext::make(['caller' => 'http', 'actor' => null]))
        ->toThrow(InvalidArgumentException::class);
});

it('fail: context build refuses unknown_caller [CTX-001]', function () {
    expect(fn () => CapabilityContext::make(['caller' => 'not-a-caller', 'actor' => H::user()]))
        ->toThrow(InvalidArgumentException::class);
});

it('fail: context build refuses missing_scope_when_required [CTX-001]', function () {
    $user = H::user(7, null);
    unset($user->current_tenant_id);
    $ctx = CapabilityContext::make(['caller' => 'http', 'actor' => $user]);
    expect($ctx->scope())->toBeNull();
    $resolver = new DefaultScopeResolver(['tenancy_required' => true, 'user_tenants' => [], 'memberships' => []]);
    expect(fn () => (new ResolveTenantFromCaller($resolver))->resolve($ctx, ['require_scope' => true, 'tenancy_required' => true]))
        ->toThrow(UnresolvedScopeException::class);
});

it('fail: context build refuses invalid_mcp_auth_profile [CTX-001]', function () {
    expect(fn () => CapabilityContext::make([
        'caller' => 'mcp',
        'actor' => H::user(),
        'mcp' => ['auth_profile' => 'bogus'],
    ]))->toThrow(InvalidArgumentException::class);
});

it('fail: context build refuses missing_job_acting_as_metadata [CTX-001]', function () {
    expect(fn () => RunCapabilityJob::assertDispatchable(['name' => 'x', 'input' => []]))
        ->toThrow(MissingJobActorException::class);
});
