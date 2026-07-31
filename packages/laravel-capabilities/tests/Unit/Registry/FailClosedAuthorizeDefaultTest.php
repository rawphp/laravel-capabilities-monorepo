<?php

// REQ-070 / L-003: Registry + makeRegistry deny by default when authorize is missing.

declare(strict_types=1);

use Rawphp\Capabilities\Boot\CapabilitiesConfig;
use Rawphp\Capabilities\Boot\ContainerBindings;
use Rawphp\Capabilities\Persistence\ArrayTableGateway;
use Rawphp\Capabilities\Registry\CapabilityDefinition;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\CapabilityResult;
use Rawphp\Capabilities\Support\CapabilityScope;
use Rawphp\Capabilities\Support\StubAuthorizer;
use Rawphp\Capabilities\Support\SystemActor;

it('REQ-070: bare CapabilityRegistry denies invoke when capability has no authorize callable', function () {
    $registry = new CapabilityRegistry;
    $registry->register(new CapabilityDefinition(
        name: 'deny-default-cap',
        description: 'no authorize',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    $result = $registry->invoke('deny-default-cap', [], [
        'caller' => 'http',
        'actor' => SystemActor::named('worker'),
        'scope' => new CapabilityScope(tenantId: 't-1'),
    ]);

    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('forbidden');
});

it('REQ-070: makeRegistry defaults deny when no host authorizer and no per-capability authorize', function () {
    // rate_limits.driver defaults to cache; unit path uses memory (L-008 / REQ-073).
    $config = array_replace_recursive(CapabilitiesConfig::defaults(), [
        'rate_limits' => ['driver' => 'memory'],
    ]);
    $registry = ContainerBindings::makeRegistry($config, new ArrayTableGateway);
    $registry->register(new CapabilityDefinition(
        name: 'deny-make-registry-cap',
        description: 'no authorize',
        readOnly: true,
        allowSystemCallers: true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    $result = $registry->invoke('deny-make-registry-cap', [], [
        'caller' => 'http',
        'actor' => SystemActor::named('worker'),
        'scope' => new CapabilityScope(tenantId: 't-1'),
    ]);

    expect($result->isOk())->toBeFalse()
        ->and($result->errorCode())->toBe('forbidden');
});

it('REQ-070: explicit withAuthorizer(allow) restores allow when authorize callable is absent', function () {
    $registry = (new CapabilityRegistry)->withAuthorizer(StubAuthorizer::allow());
    $ran = false;
    $registry->register(new CapabilityDefinition(
        name: 'allow-override-cap',
        description: 'explicit allow',
        readOnly: true,
        allowSystemCallers: true,
        run: static function () use (&$ran) {
            $ran = true;

            return CapabilityResult::ok(['ok' => true]);
        },
    ));

    $result = $registry->invoke('allow-override-cap', [], [
        'caller' => 'http',
        'actor' => SystemActor::named('worker'),
        'scope' => new CapabilityScope(tenantId: 't-1'),
    ]);

    expect($result->isOk())->toBeTrue()->and($ran)->toBeTrue();
});

it('REQ-070: per-capability authorize callable is used instead of default authorizer', function () {
    $registry = new CapabilityRegistry; // default deny
    $registry->register(new CapabilityDefinition(
        name: 'cap-authorize-true',
        description: 'callable allows',
        readOnly: true,
        allowSystemCallers: true,
        authorize: static fn () => true,
        run: static fn () => CapabilityResult::ok(['ok' => true]),
    ));

    $result = $registry->invoke('cap-authorize-true', [], [
        'caller' => 'http',
        'actor' => SystemActor::named('worker'),
        'scope' => new CapabilityScope(tenantId: 't-1'),
    ]);

    expect($result->isOk())->toBeTrue();
});
