<?php

declare(strict_types=1);

use Rawphp\Capabilities\Schema\ToolSchemaExporter;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\DiscoveryHelpers;

foreach (ToolSchemaExporter::ADAPTERS as $adapter) {
    it("happy: adapter {$adapter} uses registry JSON Schema not hand copy [D-004]", function () use ($adapter) {
        $registry = DiscoveryHelpers::registry();
        DiscoveryHelpers::fluentCreateInvoice($registry);
        $exported = $registry->toolSchemas()->export($adapter, 'create-invoice');

        expect($exported['source'])->toBe('registry')
            ->and($exported['input_schema'])->toBe(CreateInvoiceInput::jsonSchema())
            ->and($registry->toolSchemas()->assertUsesRegistry($adapter, 'create-invoice'))->toBeTrue();
    });

    it("fail: adapter {$adapter} does not maintain second schema source [D-004]", function () use ($adapter) {
        $registry = DiscoveryHelpers::registry();
        DiscoveryHelpers::fluentCreateInvoice($registry);
        $handCopy = ['type' => 'object', 'properties' => ['forged' => ['type' => 'string']]];

        expect($registry->toolSchemas()->assertUsesRegistry($adapter, 'create-invoice', $handCopy))->toBeFalse()
            ->and($registry->toolSchemas()->export($adapter, 'create-invoice')['input_schema'])
            ->not->toBe($handCopy);
    });
}
