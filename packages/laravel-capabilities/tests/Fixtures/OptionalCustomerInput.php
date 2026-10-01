<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Support\CapabilityData;

/** Optional field with a server-only rule and no nullable (D-004). */
final class OptionalCustomerInput extends CapabilityData
{
    public function __construct(
        public ?int $customer_id = null,
    ) {}

    public static function rules(): array
    {
        return [
            'customer_id' => ['exists:customers,id'],
        ];
    }
}
