<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

$matrix = [
    [true, true, true],
    [true, true, false],
    [true, false, true],
    [true, false, false],
    [false, true, true],
    [false, true, false],
    [false, false, true],
    [false, false, false],
];

foreach ($matrix as [$validateOutput, $readOnly, $hasSchema]) {
    $vo = $validateOutput ? 'True' : 'False';
    $ro = $readOnly ? 'True' : 'False';
    $hs = $hasSchema ? 'True' : 'False';
    $label = "validate_output={$vo} readOnly={$ro} has_schema={$hs}";

    $shouldValidate = $validateOutput && $hasSchema;
    // Definition policy: output declared → validate when config on; readOnly without schema → skip

    if ($shouldValidate) {
        it("happy: output validated when {$label} [D-014]", function () use ($validateOutput, $readOnly, $hasSchema) {
            $registry = DiscoveryHelpers::registry([], ['validate_output' => $validateOutput]);
            $name = 'cfg-ok-'.md5((string) $validateOutput.$readOnly.$hasSchema);
            $builder = Capability::define($name)->surfaces(['http']);
            if ($readOnly) {
                $builder->readOnly(true)->run(fn () => new CreateInvoiceResult(invoice_id: 1));
            } else {
                $builder->input(CreateInvoiceInput::class)
                    ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 1));
            }
            if ($hasSchema) {
                $builder->output(CreateInvoiceResult::class);
            }
            $builder->register($registry);

            $input = $readOnly ? [] : ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'];
            $result = $registry->invoke($name, $input);
            expect($result->isOk())->toBeTrue();
            expect($registry->get($name)->shouldValidateOutput($validateOutput))->toBeTrue();
        });

        it("fail: invalid output rejected when {$label} [D-014]", function () use ($validateOutput, $readOnly, $hasSchema) {
            $registry = DiscoveryHelpers::registry([], ['validate_output' => $validateOutput]);
            $name = 'cfg-bad-'.md5((string) $validateOutput.$readOnly.$hasSchema);
            $builder = Capability::define($name)->surfaces(['http']);
            if ($readOnly) {
                $builder->readOnly(true)->run(fn () => ['bad' => true]);
            } else {
                $builder->input(CreateInvoiceInput::class)->run(fn ($in) => ['bad' => true]);
            }
            $builder->output(CreateInvoiceResult::class)->register($registry);

            $input = $readOnly ? [] : ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'];
            $result = $registry->invoke($name, $input);
            expect($result->isOk())->toBeFalse()
                ->and($result->errorCode())->toBe('output_invalid');
        });
    } else {
        it("edge: output validation skipped or optional when {$label} [D-014]", function () use ($validateOutput, $readOnly, $hasSchema) {
            $registry = DiscoveryHelpers::registry([], ['validate_output' => $validateOutput]);
            $name = 'cfg-skip-'.md5((string) $validateOutput.$readOnly.$hasSchema);
            $builder = Capability::define($name)->surfaces(['http']);
            if ($readOnly) {
                $builder->readOnly(true)->run(fn () => ['free' => 'form']);
            } else {
                $builder->input(CreateInvoiceInput::class)->run(fn ($in) => ['free' => 'form']);
            }
            if ($hasSchema) {
                $builder->output(CreateInvoiceResult::class);
            }
            $def = $builder->register($registry);

            expect($def->shouldValidateOutput($validateOutput))->toBe($validateOutput && $hasSchema);

            $input = $readOnly ? [] : ['customer_id' => 1, 'amount_cents' => 1, 'currency' => 'USD'];
            $result = $registry->invoke($name, $input);
            // When skipped, free-form output is allowed through.
            if (! $def->shouldValidateOutput($validateOutput)) {
                expect($result->isOk())->toBeTrue();
            }
        });
    }
}
