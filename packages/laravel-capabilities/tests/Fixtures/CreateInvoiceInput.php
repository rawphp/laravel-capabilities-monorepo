<?php

declare(strict_types=1);

namespace Rawphp\Capabilities\Tests\Fixtures;

use Rawphp\Capabilities\Attributes\Field;
use Rawphp\Capabilities\Support\CapabilityData;

final class CreateInvoiceInput extends CapabilityData
{
    public function __construct(
        #[Field(description: 'Customer id within the active tenant', minimum: 1)]
        public int $customer_id,
        #[Field(minimum: 1)]
        public int $amount_cents,
        #[Field(minLength: 3, maxLength: 3)]
        public string $currency,
        #[Field(maxLength: 500)]
        public ?string $memo = null,
    ) {}

    public static function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'memo' => ['nullable', 'string', 'max:500'],
        ];
    }
}
