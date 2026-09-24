<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Support\CapabilityData;

final class TenantScopedResult extends CapabilityData
{
    public function __construct(
        public int $invoice_id,
        #[Field(description: 'Owning tenant', tenantScoped: true)]
        public ?string $tenant_id = null,
    ) {}
}
