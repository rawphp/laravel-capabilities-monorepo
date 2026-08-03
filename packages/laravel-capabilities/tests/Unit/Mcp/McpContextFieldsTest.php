<?php

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Mcp\McpAuthException;
use Rawphp\Capabilities\Adapters\Mcp\McpAuthProfileResolver;
use Rawphp\Capabilities\Adapters\Mcp\McpCredential;
use Rawphp\Capabilities\Support\CapabilityContext;
use Rawphp\Capabilities\Tests\Fixtures\AdapterHelpers;
use Rawphp\Capabilities\Tests\Fixtures\PipelineHelpers;

it('edge: mcp context may include client_id [D-023]', function () {
    $ctx = CapabilityContext::make([
        'caller' => 'mcp',
        'actor' => PipelineHelpers::userActor(),
        'mcp' => ['auth_profile' => 'user_pat', 'client_id' => 'c1'],
    ]);
    expect($ctx->mcp()['client_id'] ?? null)->toBe('c1');
});

it('edge: mcp context may include auth_profile [D-023]', function () {
    $ctx = CapabilityContext::make([
        'caller' => 'mcp',
        'actor' => PipelineHelpers::userActor(),
        'mcp' => ['auth_profile' => 'integration', 'client_id' => 'svc'],
    ]);
    expect($ctx->mcp()['auth_profile'] ?? null)->toBe('integration');
});

it('edge: mcp context may include host [D-023]', function () {
    $ctx = CapabilityContext::make([
        'caller' => 'mcp',
        'actor' => PipelineHelpers::userActor(),
        'mcp' => ['auth_profile' => 'user_delegated', 'client_id' => 'c', 'host' => 'cursor'],
    ]);
    expect($ctx->mcp()['host'] ?? null)->toBe('cursor');
});

it('edge: mcp context may include session [D-023]', function () {
    $ctx = CapabilityContext::make([
        'caller' => 'mcp',
        'actor' => PipelineHelpers::userActor(),
        'mcp' => [
            'auth_profile' => 'user_delegated',
            'client_id' => 'c',
            'session' => ['seat' => 'shared'],
        ],
    ]);
    expect($ctx->mcp()['session']['seat'] ?? null)->toBe('shared');
});

it('edge: profile user_pat with allow_integration_credentials=True [D-023]', function () {
    $resolver = new McpAuthProfileResolver([
        'allow_integration_credentials' => true,
        'default_profile' => 'user_pat',
    ]);
    $resolved = $resolver->resolve(McpCredential::userPat(AdapterHelpers::user()));
    expect($resolved['mcp']['auth_profile'])->toBe('user_pat')
        ->and($resolver->allowIntegrationCredentials())->toBeTrue();
});

it('edge: profile user_pat with allow_integration_credentials=False [D-023]', function () {
    $resolver = new McpAuthProfileResolver([
        'allow_integration_credentials' => false,
    ]);
    $resolved = $resolver->resolve(McpCredential::userPat(AdapterHelpers::user()));
    expect($resolved['mcp']['auth_profile'])->toBe('user_pat')
        ->and($resolver->allowIntegrationCredentials())->toBeFalse();
});

it('edge: profile integration with allow_integration_credentials=True [D-023]', function () {
    $resolver = new McpAuthProfileResolver([
        'allow_integration_credentials' => true,
        'integration_actors' => ['svc' => 'bot'],
    ]);
    $resolved = $resolver->resolve(McpCredential::integration('svc'));
    expect($resolved['mcp']['auth_profile'])->toBe('integration');
});

it('edge: profile integration with allow_integration_credentials=False [D-023]', function () {
    $resolver = new McpAuthProfileResolver([
        'allow_integration_credentials' => false,
        'integration_actors' => ['svc' => 'bot'],
    ]);
    expect(fn () => $resolver->resolve(McpCredential::integration('svc')))
        ->toThrow(McpAuthException::class);
});

it('edge: profile user_delegated with allow_integration_credentials=True [D-023]', function () {
    $resolver = new McpAuthProfileResolver(['allow_integration_credentials' => true]);
    $resolved = $resolver->resolve(McpCredential::userDelegated(AdapterHelpers::user(), 'c1'));
    expect($resolved['mcp']['auth_profile'])->toBe('user_delegated');
});

it('edge: profile user_delegated with allow_integration_credentials=False [D-023]', function () {
    $resolver = new McpAuthProfileResolver(['allow_integration_credentials' => false]);
    $resolved = $resolver->resolve(McpCredential::userDelegated(AdapterHelpers::user(), 'c1'));
    expect($resolved['mcp']['auth_profile'])->toBe('user_delegated');
});
