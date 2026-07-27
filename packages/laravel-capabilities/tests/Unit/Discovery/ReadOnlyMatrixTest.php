<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

$callers = ['agent', 'mcp', 'http', 'cli', 'job'];

foreach ($callers as $caller) {
    it("happy: readOnly skips audit for caller {$caller} unless forced [D-010]", function () use ($caller) {
        $registry = DiscoveryHelpers::registry();
        $def = Capability::define("ro-audit-{$caller}")
            ->readOnly(true)
            ->surfaces([$caller === 'job' ? 'job' : 'http'])
            ->register($registry);

        expect($def->shouldAudit())->toBeFalse()
            ->and($def->shouldAudit(force: true))->toBeTrue()
            ->and($caller)->toBeString();
    });

    it("happy: readOnly ignores idempotency key for caller {$caller} [D-005]", function () use ($caller) {
        $registry = DiscoveryHelpers::registry();
        $def = Capability::define("ro-idem-{$caller}")
            ->readOnly(true)
            ->surfaces(['http'])
            ->idempotent('required')
            ->register($registry);

        expect($def->shouldUseIdempotency())->toBeFalse()
            ->and($caller)->toBeString();
    });

    it("edge: readOnly may skip output validation without schema for caller {$caller} [D-014]", function () use ($caller) {
        $registry = DiscoveryHelpers::registry();
        $def = Capability::define("ro-out-{$caller}")
            ->readOnly(true)
            ->surfaces(['http'])
            ->register($registry);

        expect($def->shouldValidateOutput(true))->toBeFalse()
            ->and($def->output)->toBeNull()
            ->and($caller)->toBeString();
    });
}

it('happy: readOnly forced audit true still audits [D-010]', function () {
    $registry = DiscoveryHelpers::registry();
    $def = Capability::define('ro-force-audit')
        ->readOnly(true)
        ->surfaces(['http'])
        ->audit(['force' => true])
        ->register($registry);

    expect($def->shouldAudit())->toBeTrue();
});
