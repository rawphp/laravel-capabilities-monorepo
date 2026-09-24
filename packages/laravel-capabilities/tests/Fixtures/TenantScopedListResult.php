<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Support\CapabilityData;

final class TenantScopedListResult extends CapabilityData
{
    /**
     * @param  list<TenantScopedResult>  $invoices
     */
    public function __construct(
        #[Field(items: TenantScopedResult::class)]
        public array $invoices,
    ) {}
}
