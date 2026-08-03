<?php

declare(strict_types=1);

use Rawphp\Capabilities\Capability;
use Rawphp\Capabilities\Discovery\AttributeDiscoverer;
use Rawphp\Capabilities\Tests\Fixtures\Capabilities\AttributedCreateInvoice;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

function attributedCreateInvoiceDefinition()
{
    return (new AttributeDiscoverer)->fromClass(AttributedCreateInvoice::class);
}

function fluentCreateInvoiceDefinition()
{
    return Capability::define('create-invoice')
        ->description('Create an invoice for a customer.')
        ->surfaces(['agent', 'mcp', 'http', 'cli'])
        ->input(CreateInvoiceInput::class)
        ->output(CreateInvoiceResult::class)
        ->aliases(['invoice.create'])
        ->groups(['billing'])
        ->tags(['finance'])
        ->readOnly(false)
        ->allowSystemCallers(['billing-worker'])
        ->globalSystem(false)
        ->approvalPolicy('requester_or_role')
        ->approvalTtlHours(24)
        ->rateLimit(['per_minute' => 10])
        ->idempotent('optional')
        ->audit(true)
        ->toDefinition();
}

$fields = [
    'name' => fn ($d) => $d->name,
    'description' => fn ($d) => $d->description,
    'surfaces' => fn ($d) => $d->surfaces,
    'input' => fn ($d) => $d->input,
    'output' => fn ($d) => $d->output,
    'aliases' => fn ($d) => $d->aliases,
    'deprecated' => fn ($d) => $d->deprecated,
    'successor' => fn ($d) => $d->successor,
    'sunset_at' => fn ($d) => $d->sunset_at,
    'groups' => fn ($d) => $d->groups,
    'tags' => fn ($d) => $d->tags,
    'readOnly' => fn ($d) => $d->readOnly,
    'allowSystemCallers' => fn ($d) => $d->allowSystemCallers,
    'globalSystem' => fn ($d) => $d->globalSystem,
    'approvalPolicy' => fn ($d) => $d->approvalPolicy,
    'approvalTtlHours' => fn ($d) => $d->approvalTtlHours,
    'rateLimit' => fn ($d) => $d->rateLimit,
    'idempotent' => fn ($d) => $d->idempotent,
];

foreach ($fields as $field => $getter) {
    it("happy: fluent and attribute agree on field {$field} [D-017]", function () use ($getter) {
        $attr = attributedCreateInvoiceDefinition();
        $fluent = fluentCreateInvoiceDefinition();
        expect($attr)->not->toBeNull()
            ->and($getter($attr))->toBe($getter($fluent));
    });
}
