<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures\Outside;

use Rawphp\Capabilities\Attributes\Capability;
use Rawphp\Capabilities\Contracts\DefinesCapability;
use Rawphp\Capabilities\Tests\Fixtures\CreateInvoiceInput;

#[Capability(
    name: 'outside-cap',
    description: 'Should not be auto-discovered from path.',
    input: CreateInvoiceInput::class,
)]
final class OutsideCapability implements DefinesCapability
{
    public function run(CreateInvoiceInput $input): array
    {
        return [];
    }
}
