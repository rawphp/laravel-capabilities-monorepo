<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Events\CapabilityFailed;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

foreach (['agent', 'mcp'] as $caller) {
    it("fail: output_invalid via {$caller} is not presented as successful tool result [D-014]", function () use ($caller) {
        $registry = DiscoveryHelpers::registry();
        Capability::define("loop-bad-{$caller}")
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->run(fn ($in) => ['invoice_id' => 'x'])
            ->register($registry);

        $result = $registry->invoke("loop-bad-{$caller}", [
            'customer_id' => 1,
            'amount_cents' => 1,
            'currency' => 'USD',
        ], ['caller' => $caller]);

        $tool = (new OutputValidator)->toToolResult($result);
        expect($tool['ok'])->toBeFalse()
            ->and($tool['is_error'])->toBeTrue()
            ->and($result->errorCode())->toBe('output_invalid');
    });

    it("happy: output_invalid via {$caller} emits CapabilityFailed [D-014]", function () use ($caller) {
        $registry = DiscoveryHelpers::registry();
        Capability::define("loop-evt-{$caller}")
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->run(fn ($in) => [])
            ->register($registry);

        $registry->invoke("loop-evt-{$caller}", [
            'customer_id' => 1,
            'amount_cents' => 1,
            'currency' => 'USD',
        ], ['caller' => $caller]);

        expect($registry->failedEvents())->not->toBeEmpty()
            ->and($registry->failedEvents()[0])->toBeInstanceOf(CapabilityFailed::class)
            ->and($registry->failedEvents()[0]->code)->toBe('output_invalid');
    });

    it("happy: output_invalid via {$caller} is logged [D-014]", function () use ($caller) {
        $registry = DiscoveryHelpers::registry();
        Capability::define("loop-log-{$caller}")
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->run(fn ($in) => [])
            ->register($registry);

        $registry->invoke("loop-log-{$caller}", [
            'customer_id' => 1,
            'amount_cents' => 1,
            'currency' => 'USD',
        ], ['caller' => $caller]);

        expect($registry->logs())->not->toBeEmpty()
            ->and($registry->logs()[0]['level'])->toBe('error')
            ->and($registry->logs()[0]['context']['code'])->toBe('output_invalid');
    });
}
