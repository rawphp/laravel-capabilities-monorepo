<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures\Capabilities;

use Rawphp\Capabilities\Attributes\Capability;
use Rawphp\Capabilities\Contracts\DefinesCapability;

#[Capability(
    name: 'list-customers',
    description: 'List customers.',
    surfaces: ['agent', 'mcp', 'http'],
    readOnly: true,
    idempotent: 'none',
)]
final class AttributedListCustomers implements DefinesCapability
{
    public function run(): array
    {
        return ['customers' => []];
    }
}
