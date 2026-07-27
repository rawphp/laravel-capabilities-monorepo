<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures\Capabilities;

use Rawphp\Capabilities\Attributes\Capability;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

#[Capability(
    name: 'create-invoice',
    description: 'Create an invoice for a customer.',
    surfaces: ['agent', 'mcp', 'http', 'cli'],
    input: CreateInvoiceInput::class,
    output: CreateInvoiceResult::class,
    aliases: ['invoice.create'],
    groups: ['billing'],
    tags: ['finance'],
    readOnly: false,
    allowSystemCallers: ['billing-worker'],
    globalSystem: false,
    approvalPolicy: 'requester_or_role',
    approvalTtlHours: 24,
    rateLimit: ['per_minute' => 10],
    idempotent: 'optional',
    audit: true,
)]
final class AttributedCreateInvoice implements DefinesCapability
{
    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: 42);
    }
}
