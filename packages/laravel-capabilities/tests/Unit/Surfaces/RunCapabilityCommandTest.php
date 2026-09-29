<?php

// REQ-024 / D-002 / D-016: `capability:run` ops command end-to-end on a bare container. Unit-only.

declare(strict_types=1);

use Rawphp\Capabilities\Adapters\Artisan\RunCapabilityCommand;
use Rawphp\Capabilities\Registry\CapabilityRegistry;
use Rawphp\Capabilities\Support\SystemActor;
use Rawphp\Capabilities\Tests\Fixtures\ArtisanCommandHarness;
use Rawphp\Capabilities\Tests\Fixtures\ScopeCallerJobHelpers as H;

it('runs a capability as --system and prints the result envelope [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand($h['registry']), [
        'name' => $h['name'],
        '--system' => 'scheduler',
        '--tenant' => 'tenant-a',
        '--input' => json_encode(H::homeInput()),
    ]);

    expect($r['exit'])->toBe(0)
        ->and(json_decode($r['output'], true))->toMatchArray(['ok' => true])
        ->and($h['registry']->lastState()?->context?->caller())->toBe('artisan')
        ->and($h['registry']->lastState()?->context?->actor())->toBeInstanceOf(SystemActor::class);
});

it('runs a capability as --acting-as, resolving the real user through the registry requester resolver [D-002 / L-107]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $seen = [];
    $user = H::user(1, 'tenant-a');
    $h['registry']->withRequesterResolver(function (string $type, string $id) use (&$seen, $user): ?object {
        $seen[] = [$type, $id];

        return $type === 'user' && $id === '1' ? $user : null;
    });

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand($h['registry']), [
        'name' => $h['name'],
        '--acting-as' => '1',
        '--tenant' => 'tenant-a',
        '--input' => json_encode(H::homeInput()),
    ]);

    expect($r['exit'])->toBe(0)
        ->and($seen)->toBe([['user', '1']])
        ->and($h['registry']->lastState()?->context?->actor())->toBe($user);
});

it('fails closed when --acting-as names a user the resolver cannot find [D-002 / L-107]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);
    $h['registry']->withRequesterResolver(static fn (): ?object => null);

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand($h['registry']), [
        'name' => $h['name'],
        '--acting-as' => '404',
        '--input' => json_encode(H::homeInput()),
    ]);

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('404')
        ->and($h['registry']->lastState())->toBeNull();
});

it('fails closed when --acting-as is given but the registry has no requester resolver [D-002 / L-107]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand($h['registry']), [
        'name' => $h['name'],
        '--acting-as' => '1',
        '--input' => json_encode(H::homeInput()),
    ]);

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('user_resolver')
        ->and($h['registry']->lastState())->toBeNull();
});

it('fails without --acting-as or --system and never runs the capability [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand($h['registry']), [
        'name' => $h['name'],
        '--input' => json_encode(H::homeInput()),
    ]);

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('--acting-as')
        ->and($h['registry']->lastState())->toBeNull();
});

it('fails when both --acting-as and --system are given [D-002]', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand($h['registry']), [
        'name' => $h['name'],
        '--acting-as' => '1',
        '--system' => 'scheduler',
    ]);

    expect($r['exit'])->toBe(1)
        ->and($h['registry']->lastState())->toBeNull();
});

it('fails when --input is not a JSON object', function () {
    $h = H::scopeHarness(['allowSystemCallers' => true]);

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand($h['registry']), [
        'name' => $h['name'],
        '--system' => 'scheduler',
        '--input' => '"just a string"',
    ]);

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('Option --input must be a JSON object.');
});

it('reports the capability error message and exits non-zero on a failed result', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['billing-bot']]);

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand($h['registry']), [
        'name' => $h['name'],
        '--system' => 'scheduler',
        '--tenant' => 'tenant-a',
        '--input' => json_encode(H::homeInput()),
    ]);

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('SystemActor "scheduler" is not allowed');
});

it('resolves the registry from the container when none is injected', function () {
    $h = H::scopeHarness(['allowSystemCallers' => ['scheduler']]);

    $r = ArtisanCommandHarness::run(new RunCapabilityCommand, [
        'name' => $h['name'],
        '--system' => 'scheduler',
        '--tenant' => 'tenant-a',
        '--input' => json_encode(H::homeInput()),
    ], [CapabilityRegistry::class => $h['registry']]);

    expect($r['exit'])->toBe(0);
});

it('fails closed when the registry cannot be resolved', function () {
    $r = ArtisanCommandHarness::run(new RunCapabilityCommand, ['name' => 'anything', '--system' => 'scheduler'], [
        CapabilityRegistry::class => static fn () => throw new RuntimeException('boom'),
    ]);

    expect($r['exit'])->toBe(1)
        ->and($r['output'])->toContain('CapabilityRegistry is not bound.');
});
