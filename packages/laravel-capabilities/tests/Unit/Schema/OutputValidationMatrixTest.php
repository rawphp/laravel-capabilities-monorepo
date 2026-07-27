<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Schema\OutputValidator;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

$callers = ['agent', 'mcp', 'http', 'cli', 'job'];

foreach ($callers as $caller) {
    it("fail: invalid output via {$caller} is not success [D-014]", function () use ($caller) {
        $registry = DiscoveryHelpers::registry();
        Capability::define("matrix-bad-{$caller}")
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->run(fn ($in) => ['invoice_id' => 'bad'])
            ->register($registry);

        $result = $registry->invoke("matrix-bad-{$caller}", [
            'customer_id' => 1,
            'amount_cents' => 1,
            'currency' => 'USD',
        ], ['caller' => $caller]);

        expect($result->isOk())->toBeFalse()
            ->and($result->errorCode())->toBe('output_invalid');

        if (in_array($caller, ['agent', 'mcp'], true)) {
            $tool = (new OutputValidator)->toToolResult($result);
            expect($tool['is_error'])->toBeTrue();
        }
        if ($caller === 'http') {
            expect((new OutputValidator)->toHttpEnvelope($result)['status'])->toBe(500);
        }
    });

    it("happy: valid output via {$caller} succeeds [D-014]", function () use ($caller) {
        $registry = DiscoveryHelpers::registry();
        Capability::define("matrix-ok-{$caller}")
            ->input(CreateInvoiceInput::class)
            ->output(CreateInvoiceResult::class)
            ->run(fn ($in) => new CreateInvoiceResult(invoice_id: 3))
            ->register($registry);

        $result = $registry->invoke("matrix-ok-{$caller}", [
            'customer_id' => 1,
            'amount_cents' => 1,
            'currency' => 'USD',
        ], ['caller' => $caller]);

        expect($result->isOk())->toBeTrue();
    });
}
