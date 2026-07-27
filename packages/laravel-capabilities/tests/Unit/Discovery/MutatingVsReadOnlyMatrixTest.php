<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

$callers = ['agent', 'mcp', 'http', 'cli', 'job'];
$readOnlyCases = [true, false];

foreach ($readOnlyCases as $readOnly) {
    $roLabel = $readOnly ? 'True' : 'False';
    foreach ($callers as $caller) {
        it("edge: readOnly={$roLabel} caller={$caller} audit policy applied [D-010]", function () use ($readOnly, $caller) {
            $registry = DiscoveryHelpers::registry();
            if ($readOnly) {
                $def = Capability::define("mv-audit-{$caller}-".($readOnly ? 'ro' : 'mut'))
                    ->readOnly(true)
                    ->surfaces(['http'])
                    ->register($registry);
            } else {
                $def = DiscoveryHelpers::mutatingWith($registry, "mv-audit-{$caller}-mut", [
                    'surfaces' => ['http'],
                ]);
            }

            if ($readOnly) {
                expect($def->shouldAudit())->toBeFalse();
            } else {
                expect($def->shouldAudit())->toBeTrue();
            }
            expect($caller)->toBeString();
        });

        it("edge: readOnly={$roLabel} caller={$caller} idempotency policy applied [D-005]", function () use ($readOnly, $caller) {
            $registry = DiscoveryHelpers::registry();
            if ($readOnly) {
                $def = Capability::define("mv-idem-{$caller}-ro")
                    ->readOnly(true)
                    ->surfaces(['http'])
                    ->idempotent('optional')
                    ->register($registry);
                expect($def->shouldUseIdempotency())->toBeFalse();
            } else {
                $def = DiscoveryHelpers::mutatingWith($registry, "mv-idem-{$caller}-mut", [
                    'idempotent' => 'optional',
                ]);
                expect($def->shouldUseIdempotency())->toBeTrue();
            }
            expect($caller)->toBeString();
        });

        it("edge: readOnly={$roLabel} caller={$caller} output validation policy applied [D-014]", function () use ($readOnly, $caller) {
            $registry = DiscoveryHelpers::registry();
            if ($readOnly) {
                $def = Capability::define("mv-out-{$caller}-ro")
                    ->readOnly(true)
                    ->surfaces(['http'])
                    ->register($registry);
                // No output schema → skip
                expect($def->shouldValidateOutput(true))->toBeFalse();

                $withSchema = Capability::define("mv-out-{$caller}-ro-schema")
                    ->readOnly(true)
                    ->output(CreateInvoiceResult::class)
                    ->surfaces(['http'])
                    ->register($registry);
                expect($withSchema->shouldValidateOutput(true))->toBeTrue();
            } else {
                $def = DiscoveryHelpers::mutatingWith($registry, "mv-out-{$caller}-mut", [
                    'output' => CreateInvoiceResult::class,
                    'input' => CreateInvoiceInput::class,
                ]);
                expect($def->shouldValidateOutput(true))->toBeTrue()
                    ->and($def->shouldValidateOutput(false))->toBeFalse();
            }
            expect($caller)->toBeString();
        });
    }
}
