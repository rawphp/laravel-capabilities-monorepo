<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures\Capabilities;

use Rawphp\Capabilities\Attributes\Capability;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceResult;

#[Capability(
    name: 'create-invoice-cli',
    description: 'Create an invoice with CLI routing.',
    surfaces: ['agent', 'mcp', 'http', 'cli'],
    input: CreateInvoiceInput::class,
    output: CreateInvoiceResult::class,
    cliDomain: 'invoices',
    cliVerb: 'create',
)]
final class AttributedCreateInvoiceWithCli implements DefinesCapability
{
    public function run(CreateInvoiceInput $input): CreateInvoiceResult
    {
        return new CreateInvoiceResult(invoice_id: 42);
    }
}
